<?php

namespace App\Integracoes\Aplicacao;

use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\Direcao;
use App\Integracoes\Dominio\Enums\EstadoHomologacao;
use App\Integracoes\Dominio\Enums\EstadoSaude;
use App\Integracoes\Dominio\Enums\OperacaoIntegracao;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Integracoes\Dominio\Enums\StatusPontoAcesso;
use App\Integracoes\Dominio\Enums\TipoPontoAcesso;
use App\Integracoes\Dominio\Excecoes\PayloadDivergente;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\Equipamento;
use App\Models\EquipamentoCapacidade;
use App\Models\HistoricoAcesso;
use App\Models\Pessoa;
use App\Models\PontoAcesso;
use App\Models\Posto;
use App\Models\User;
use App\Models\Vinculo;
use App\Services\AuditService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Liberação manual operacional da portaria (ADR-017 1.1.0, SDV-OPE-017/018).
 *
 * - O posto vem do turno ativo do operador (resolvido no backend), não do
 *   navegador. Ele define os relés PREFERENCIAIS: a Guarita Principal alcança
 *   todos os relés autorizados; Veículos e Pedestres mostram os seus primeiro.
 * - Relé fora do posto exige `acessos.liberar-outros-reles` e confirmação
 *   destacada, e o evento registra que estava fora do posto.
 * - Exceção por checkbox: permissão própria, motivo padronizado
 *   `liberacao_excepcional_operador`, observação opcional (ou obrigatória por
 *   configuração), confirmação destacada e auditoria restrita.
 *
 * O navegador informa só ids e dados operacionais. Implantação, posto,
 * pessoa, ponto, endereço, porta, certificado, credencial, adaptador,
 * homologação, situação, direção e estado online são relidos aqui.
 */
class LiberacaoManual
{
    public const RESULTADO_DECISAO = 'liberado';

    public function __construct(
        private OutboxIntegracao $outbox,
        private AuditService $audit,
        private TurnosOperacionais $turnos,
    ) {}

    /**
     * Relés que o operador pode acionar a partir do posto ativo: os do posto
     * primeiro e, com permissão ampliada, os outros pontos da implantação.
     * Só entram terminais que cumprem todos os requisitos técnicos.
     *
     * @return array{preferenciais: list<array{equipamento: Equipamento, ponto: PontoAcesso, do_posto: bool}>,
     *     outros: list<array{equipamento: Equipamento, ponto: PontoAcesso, do_posto: bool}>}
     */
    public function equipamentosDisponiveis(User $operador, Posto $posto, Direcao $direcao): array
    {
        $resultado = ['preferenciais' => [], 'outros' => []];
        if (! $operador->hasPermission(ComandosAbertura::PERMISSAO_LIBERACAO_MANUAL)) {
            return $resultado;
        }

        $pontos = PontoAcesso::query()->with('vinculoVigente.equipamento')->orderBy('nome')->get();
        foreach ($pontos as $ponto) {
            $equipamento = $ponto->vinculoVigente?->equipamento;
            if ($equipamento === null || $this->bloqueiosTecnicos($equipamento, $direcao) !== []) {
                continue;
            }

            $doPosto = $this->pertenceAoPosto($posto, $ponto);
            if (! $doPosto && ! $operador->hasPermission(ComandosAbertura::PERMISSAO_LIBERAR_OUTROS_RELES)) {
                continue;
            }

            $resultado[$doPosto ? 'preferenciais' : 'outros'][] = ['equipamento' => $equipamento, 'ponto' => $ponto, 'do_posto' => $doPosto];
        }

        foreach ($resultado as &$lista) {
            usort($lista, fn (array $a, array $b) => [$this->afinidade($posto, $a['ponto']), $a['ponto']->nome] <=> [$this->afinidade($posto, $b['ponto']), $b['ponto']->nome]);
        }

        return $resultado;
    }

    /**
     * Requisitos técnicos do terminal para a direção escolhida. Vazio = apto.
     *
     * @return array<string, string>
     */
    public function bloqueiosTecnicos(Equipamento $equipamento, Direcao $direcao): array
    {
        $ponto = $equipamento->pontoVigente();
        $capacidade = EquipamentoCapacidade::query()
            ->where('equipamento_id', $equipamento->id)
            ->where('capacidade', Capacidade::AberturaRemota->value)
            ->first();

        return array_filter([
            'liberacao_operacional_desabilitada' => ! config('integracoes.liberacao_operacional_habilitada')
                ? 'Liberação manual operacional desligada nesta instalação.' : null,
            'sem_ponto' => $ponto === null || $ponto->tipo === TipoPontoAcesso::Bancada
                ? 'O terminal não está em um ponto de acesso real.' : null,
            'ponto_nao_operacional' => $ponto !== null && $ponto->status !== StatusPontoAcesso::Ativo
                ? 'O ponto de acesso não está ativo.' : null,
            'equipamento_nao_operacional' => $equipamento->status !== StatusEquipamento::Ativo
                ? 'O terminal não está ativo para operação.' : null,
            'equipamento_offline' => $equipamento->estado_saude !== EstadoSaude::Conectado
                ? 'O terminal está offline.' : null,
            'certificado_nao_confiavel' => $equipamento->esquema === 'https' && $equipamento->tls_pin_sha256 === null
                ? 'O certificado HTTPS do terminal não foi confiado.' : null,
            'firmware_nao_homologado' => $capacidade === null
                || $equipamento->firmware_versao === null
                || $capacidade->firmware_versao !== $equipamento->firmware_versao
                || $capacidade->estado_homologacao !== EstadoHomologacao::Homologada
                ? 'A abertura remota não está homologada para o firmware deste terminal.' : null,
            'capacidade_ausente' => ! $equipamento->suporta(Capacidade::AberturaRemota)
                ? 'O terminal não confirmou a abertura remota.' : null,
            'rele_nao_homologado' => $equipamento->rele_homologado_em === null
                ? 'O relé deste terminal ainda não foi homologado.' : null,
            'direcao_incompativel' => $ponto === null
                || ! $direcao->compativelCom($equipamento->direcao)
                || ! $direcao->compativelCom($ponto->direcao_suportada)
                ? 'O terminal ou o ponto não atende a direção escolhida.' : null,
        ]);
    }

    /**
     * Bloqueios completos para o operador, o posto ativo e a direção.
     *
     * @return array<string, string>
     */
    public function bloqueios(Equipamento $equipamento, User $operador, Posto $posto, Direcao $direcao): array
    {
        $ponto = $equipamento->pontoVigente();
        $foraDoPosto = $ponto === null || ! $this->pertenceAoPosto($posto, $ponto);

        return array_filter([
            'sem_permissao' => ! $operador->hasPermission(ComandosAbertura::PERMISSAO_LIBERACAO_MANUAL)
                ? 'Você não tem a permissão acessos.liberar-reles-do-posto.' : null,
            'fora_do_posto_sem_permissao' => $foraDoPosto && ! $operador->hasPermission(ComandosAbertura::PERMISSAO_LIBERAR_OUTROS_RELES)
                ? 'Este ponto não pertence ao seu posto e você não tem a permissão acessos.liberar-outros-reles.' : null,
        ]) + $this->bloqueiosTecnicos($equipamento, $direcao);
    }

    /** A Guarita Principal alcança todos os pontos; os demais, os vinculados a eles. */
    public function pertenceAoPosto(Posto $posto, PontoAcesso $ponto): bool
    {
        return $posto->principal() || $posto->pontosVigentes()->whereKey($ponto->id)->exists();
    }

    /** @return array<string, string> restrições da pessoa para a liberação normal */
    public function restricoesDaPessoa(Pessoa $pessoa): array
    {
        $vinculo = Vinculo::query()->where('pessoa_id', $pessoa->id)->whereNull('ended_at')->where('status', 'ativo')->first();

        return array_filter([
            'pessoa_inativa' => $pessoa->status !== 'ativo' ? 'Cadastro da pessoa não está ativo ('.$pessoa->status.').' : null,
            'sem_vinculo_vigente' => $vinculo === null ? 'A pessoa não tem vínculo vigente com um imóvel.' : null,
        ]);
    }

    /**
     * @param  array{equipamento_id: string, direcao: string, pessoa_id?: ?string, motivo?: ?string,
     *     excepcional?: bool, observacao?: ?string, identificacao?: ?string, confirmou_fora_do_posto?: bool}  $dados
     *     Qualquer outra chave (IP, porta, credencial...) é ignorada.
     */
    public function solicitar(User $operador, array $dados, string $chaveIdempotencia): HistoricoAcesso
    {
        $excepcional = (bool) ($dados['excepcional'] ?? false);
        $direcao = Direcao::tryFrom((string) ($dados['direcao'] ?? ''));
        if (! in_array($direcao, [Direcao::Entrada, Direcao::Saida], true)) {
            throw new RegraIntegracaoViolada('direcao_invalida', 'Escolha entrada ou saída.');
        }
        if (! preg_match('/^[A-Za-z0-9:_-]{8,120}$/', $chaveIdempotencia)) {
            throw new RegraIntegracaoViolada('chave_invalida', 'Reabra a confirmação e tente novamente.');
        }

        $turno = $this->turnos->turnoAtivo($operador)
            ?? throw new RegraIntegracaoViolada('sem_posto_ativo', 'Selecione o posto em que você está trabalhando.');
        $posto = $turno->posto;

        // Relidos na implantação atual: id de outra implantação não é encontrado.
        $equipamento = Equipamento::query()->findOrFail($dados['equipamento_id']);
        $pessoa = ! empty($dados['pessoa_id']) ? Pessoa::query()->findOrFail($dados['pessoa_id']) : null;

        $existente = HistoricoAcesso::query()->where('chave_idempotencia', $chaveIdempotencia)->first();
        if ($existente !== null) {
            return $this->mesmaLiberacaoOuFalha($existente, $equipamento, $pessoa, $direcao, $excepcional, $chaveIdempotencia);
        }

        if (($bloqueios = $this->bloqueios($equipamento, $operador, $posto, $direcao)) !== []) {
            throw new RegraIntegracaoViolada(array_key_first($bloqueios), reset($bloqueios));
        }

        $ponto = $equipamento->pontoVigente();
        $foraDoPosto = ! $this->pertenceAoPosto($posto, $ponto);
        if ($foraDoPosto && ! ($dados['confirmou_fora_do_posto'] ?? false)) {
            throw new RegraIntegracaoViolada('confirmacao_fora_do_posto', "Você está operando no posto {$posto->nome} e selecionou o ponto {$ponto->nome}. Confirme o acionamento deste relé.");
        }

        $observacao = trim((string) ($dados['observacao'] ?? ''));
        $identificacao = trim((string) ($dados['identificacao'] ?? '')) ?: null;
        if ($excepcional) {
            if (! $operador->hasPermission(ComandosAbertura::PERMISSAO_LIBERACAO_EXCEPCIONAL)) {
                throw new RegraIntegracaoViolada('sem_permissao_excepcional', 'Você não tem a permissão acessos.liberar-excepcionalmente.');
            }
            if (config('integracoes.liberacao_excepcional_observacao_obrigatoria') && mb_strlen($observacao) < 5) {
                throw new RegraIntegracaoViolada('sem_observacao', 'Nesta instalação a liberação excepcional exige uma observação.');
            }
            $motivo = ComandosAbertura::MOTIVO_EXCEPCIONAL;
        } else {
            if ($pessoa === null) {
                throw new RegraIntegracaoViolada('sem_pessoa', 'Selecione a pessoa ou marque a liberação excepcional.');
            }
            if (($restricoes = $this->restricoesDaPessoa($pessoa)) !== []) {
                throw new RegraIntegracaoViolada(array_key_first($restricoes), reset($restricoes).' Para prosseguir, use a liberação excepcional.');
            }
            $motivo = trim((string) ($dados['motivo'] ?? ''));
            if (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 500) {
                throw new RegraIntegracaoViolada('sem_motivo', 'Informe o motivo da liberação (de 5 a 500 caracteres).');
            }
        }
        if (mb_strlen($observacao) > 1000 || mb_strlen((string) $identificacao) > 200) {
            throw new RegraIntegracaoViolada('texto_longo', 'Observação ou identificação longa demais.');
        }

        $vinculo = $pessoa ? Vinculo::query()->where('pessoa_id', $pessoa->id)->whereNull('ended_at')->where('status', 'ativo')->first() : null;

        try {
            return DB::transaction(function () use ($operador, $turno, $posto, $equipamento, $ponto, $pessoa, $vinculo, $direcao, $motivo, $excepcional, $identificacao, $observacao, $foraDoPosto, $chaveIdempotencia): HistoricoAcesso {
                $evento = HistoricoAcesso::query()->create([
                    'pessoa_id' => $pessoa?->id,
                    'imovel_id' => $vinculo?->imovel_id,
                    'ponto_acesso' => $ponto->nome,
                    'tipo' => $direcao->value,
                    'resultado' => self::RESULTADO_DECISAO,
                    'origem' => ComandosAbertura::ORIGEM_LIBERACAO_MANUAL,
                    'excepcional' => $excepcional,
                    'identificacao_excepcional' => $excepcional ? $identificacao : null,
                    'justificativa_excepcional' => $excepcional && $observacao !== '' ? $observacao : null,
                    'motivo' => $motivo,
                    'posto_id' => $posto->id,
                    'operador_turno_id' => $turno->id,
                    'fora_do_posto' => $foraDoPosto,
                    'ponto_acesso_id' => $ponto->id,
                    'equipamento_id' => $equipamento->id,
                    'operator_id' => $operador->id,
                    'protocol' => 'SRA-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
                    'notes' => ! $excepcional && $observacao !== '' ? $observacao : null,
                    'resultado_tecnico' => 'solicitada',
                    'chave_idempotencia' => $chaveIdempotencia,
                    'occurred_at' => now(),
                ]);

                $operacao = $this->outbox->registrar(
                    $equipamento,
                    OperacaoIntegracao::AberturaRemota,
                    [
                        'ponto_acesso_id' => $ponto->id,
                        'porta_rele' => $equipamento->porta_rele,
                        'decisao_referencia' => $evento->id,
                        'direcao' => $direcao->value,
                    ],
                    $chaveIdempotencia,
                    agregadoTipo: 'historico_acesso',
                    agregadoId: $evento->id,
                    ator: $operador,
                    expiraEm: now()->addSeconds((int) config('integracoes.abertura_validade_segundos')),
                    origem: ComandosAbertura::ORIGEM_LIBERACAO_MANUAL,
                );

                $evento->forceFill(['operacao_integracao_id' => $operacao->id, 'correlation_id' => $operacao->correlation_id])->save();

                $this->audit->record(
                    action: $excepcional ? 'liberacao_manual_excepcional_solicitada' : 'liberacao_manual_solicitada',
                    module: 'acessos',
                    entityType: 'historico_acessos',
                    entityId: $evento->id,
                    result: 'solicitado',
                    justification: $excepcional && $observacao !== '' ? $motivo.' | Observação: '.$observacao : $motivo,
                    classification: 'restrita',
                    metadata: [
                        'protocolo' => $evento->protocol,
                        'implantacao_id' => $equipamento->implantacao_id,
                        'posto_id' => $posto->id,
                        'posto' => $posto->nome,
                        'operador_turno_id' => $turno->id,
                        'fora_do_posto' => $foraDoPosto,
                        'equipamento_id' => $equipamento->id,
                        'equipamento' => $equipamento->nome,
                        'ponto_acesso_id' => $ponto->id,
                        'ponto_acesso' => $ponto->nome,
                        'porta_rele' => $equipamento->porta_rele,
                        'direcao' => $direcao->value,
                        'pessoa_id' => $pessoa?->id,
                        'imovel_id' => $vinculo?->imovel_id,
                        'excepcional' => $excepcional,
                        'motivo_padronizado' => $excepcional ? ComandosAbertura::MOTIVO_EXCEPCIONAL : null,
                        'identificacao_excepcional' => $excepcional ? $identificacao : null,
                        'operacao_id' => $operacao->id,
                        'correlation_id' => $operacao->correlation_id,
                        'chave_idempotencia' => $chaveIdempotencia,
                        'origem' => ComandosAbertura::ORIGEM_LIBERACAO_MANUAL,
                        'ip_operador' => request()?->ip(),
                    ],
                    implantacaoId: $equipamento->implantacao_id,
                );

                return $evento;
            });
        } catch (UniqueConstraintViolationException) {
            // Duas requisições com a mesma chave ao mesmo tempo (clique duplo).
            $concorrente = HistoricoAcesso::query()->where('chave_idempotencia', $chaveIdempotencia)->firstOrFail();

            return $this->mesmaLiberacaoOuFalha($concorrente, $equipamento, $pessoa, $direcao, $excepcional, $chaveIdempotencia);
        }
    }

    /** Veículos priorizam cancelas; Pedestres priorizam catracas. */
    private function afinidade(Posto $posto, PontoAcesso $ponto): int
    {
        $preferido = match ($posto->tipo) {
            Posto::TIPO_VEICULOS => TipoPontoAcesso::Cancela,
            Posto::TIPO_PEDESTRES => TipoPontoAcesso::Catraca,
            default => null,
        };

        return $preferido === null || $ponto->tipo === $preferido ? 0 : 1;
    }

    private function mesmaLiberacaoOuFalha(HistoricoAcesso $existente, Equipamento $equipamento, ?Pessoa $pessoa, Direcao $direcao, bool $excepcional, string $chave): HistoricoAcesso
    {
        $mesma = $existente->equipamento_id === $equipamento->id
            && $existente->pessoa_id === $pessoa?->id
            && $existente->tipo === $direcao->value
            && (bool) $existente->excepcional === $excepcional;

        return $mesma ? $existente : throw new PayloadDivergente($chave);
    }
}
