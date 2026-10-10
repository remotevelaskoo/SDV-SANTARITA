<?php

namespace App\Integracoes\Aplicacao;

use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\Direcao;
use App\Integracoes\Dominio\Enums\EstadoHomologacao;
use App\Integracoes\Dominio\Enums\EstadoSaude;
use App\Integracoes\Dominio\Enums\OperacaoIntegracao;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Integracoes\Dominio\Enums\StatusPontoAcesso;
use App\Integracoes\Dominio\Excecoes\PayloadDivergente;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\Equipamento;
use App\Models\EquipamentoCapacidade;
use App\Models\HistoricoAcesso;
use App\Models\OperadorPosto;
use App\Models\Pessoa;
use App\Models\PontoAcesso;
use App\Models\Posto;
use App\Models\User;
use App\Models\Vinculo;
use App\Services\AuditService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Liberação manual operacional da portaria (ADR-017).
 *
 * Diferente do teste do relé (TesteReleBancada): parte do Modo Portaria,
 * exige pessoa identificada (ou o fluxo excepcional, separado e reforçado),
 * cria evento de acesso e só aciona terminais ATIVOS do posto do operador.
 *
 * O navegador informa só ids e dados operacionais. Implantação, ponto,
 * endereço, porta, certificado, credencial, adaptador, capacidade, situação
 * e direção são relidos do cadastro a cada pedido.
 */
class LiberacaoManual
{
    public const RESULTADO_DECISAO = 'liberado';

    public function __construct(private OutboxIntegracao $outbox, private AuditService $audit) {}

    /** @return Collection<int, Posto> postos ativos em que o operador atua agora */
    public function postosDoOperador(User $operador): Collection
    {
        return Posto::query()
            ->where('status', 'ativo')
            ->whereIn('id', OperadorPosto::query()->where('user_id', $operador->id)->whereNull('ended_at')->select('posto_id'))
            ->orderBy('nome')
            ->get();
    }

    /**
     * Terminais que o operador pode acionar neste posto e direção. Só entram
     * os que cumprem todos os requisitos; os demais nem aparecem.
     *
     * @return list<array{equipamento: Equipamento, ponto: PontoAcesso}>
     */
    public function equipamentosPermitidos(User $operador, Posto $posto, Direcao $direcao): array
    {
        if (! $this->operadorNoPosto($operador, $posto)) {
            return [];
        }

        $permitidos = [];
        foreach ($posto->pontosVigentes()->orderBy('nome')->get() as $ponto) {
            $equipamento = $ponto->vinculoVigente?->equipamento;
            if ($equipamento !== null && $this->bloqueios($equipamento, $operador, $posto, $direcao) === []) {
                $permitidos[] = ['equipamento' => $equipamento, 'ponto' => $ponto];
            }
        }

        return $permitidos;
    }

    /**
     * Motivos que impedem acionar este terminal agora. Vazio = permitido.
     *
     * @return array<string, string>
     */
    public function bloqueios(Equipamento $equipamento, User $operador, Posto $posto, Direcao $direcao): array
    {
        $ponto = $equipamento->pontoVigente();
        $capacidade = EquipamentoCapacidade::query()
            ->where('equipamento_id', $equipamento->id)
            ->where('capacidade', Capacidade::AberturaRemota->value)
            ->first();

        return array_filter([
            'sem_permissao' => ! $operador->hasPermission(ComandosAbertura::PERMISSAO_LIBERACAO_MANUAL)
                ? 'Você não tem a permissão acessos.liberar-manualmente.' : null,
            'liberacao_operacional_desabilitada' => ! config('integracoes.liberacao_operacional_habilitada')
                ? 'Liberação manual operacional desligada nesta instalação.' : null,
            'posto_nao_autorizado' => ! $this->operadorNoPosto($operador, $posto)
                ? 'Você não está designado para este posto.' : null,
            'ponto_fora_do_posto' => $ponto === null || ! $posto->pontosVigentes()->whereKey($ponto->id)->exists()
                ? 'Este ponto de acesso não pertence ao seu posto.' : null,
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
     * Situação da pessoa para a liberação normal: vínculo vigente e cadastro
     * ativo. Sem isso, só o fluxo excepcional é possível.
     *
     * @return array<string, string> restrições encontradas
     */
    public function restricoesDaPessoa(Pessoa $pessoa): array
    {
        $vinculo = Vinculo::query()->where('pessoa_id', $pessoa->id)->whereNull('ended_at')->where('status', 'ativo')->first();

        return array_filter([
            'pessoa_inativa' => $pessoa->status !== 'ativo' ? 'Cadastro da pessoa não está ativo ('.$pessoa->status.').' : null,
            'sem_vinculo_vigente' => $vinculo === null ? 'A pessoa não tem vínculo vigente com um imóvel.' : null,
        ]);
    }

    /**
     * @param  array{posto_id: string, equipamento_id: string, direcao: string, pessoa_id?: ?string,
     *     motivo: string, excepcional?: bool, identificacao?: ?string, justificativa?: ?string}  $dados
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

        // Relidos na implantação atual: id de outra implantação não é encontrado.
        $posto = Posto::query()->findOrFail($dados['posto_id']);
        $equipamento = Equipamento::query()->findOrFail($dados['equipamento_id']);
        $pessoa = ! $excepcional && ! empty($dados['pessoa_id']) ? Pessoa::query()->findOrFail($dados['pessoa_id']) : null;

        $existente = HistoricoAcesso::query()->where('chave_idempotencia', $chaveIdempotencia)->first();
        if ($existente !== null) {
            return $this->mesmaLiberacaoOuFalha($existente, $equipamento, $pessoa, $direcao, $excepcional, $chaveIdempotencia);
        }

        if (($bloqueios = $this->bloqueios($equipamento, $operador, $posto, $direcao)) !== []) {
            throw new RegraIntegracaoViolada(array_key_first($bloqueios), reset($bloqueios));
        }

        $motivo = trim((string) ($dados['motivo'] ?? ''));
        if (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 500) {
            throw new RegraIntegracaoViolada('sem_motivo', 'Informe o motivo da liberação (de 5 a 500 caracteres).');
        }

        $identificacao = null;
        $justificativa = null;
        if ($excepcional) {
            if (! $operador->hasPermission(ComandosAbertura::PERMISSAO_LIBERACAO_EXCEPCIONAL)) {
                throw new RegraIntegracaoViolada('sem_permissao_excepcional', 'Você não tem a permissão acessos.liberar-excepcionalmente.');
            }
            $identificacao = trim((string) ($dados['identificacao'] ?? ''));
            $justificativa = trim((string) ($dados['justificativa'] ?? ''));
            if (mb_strlen($identificacao) < 3 || mb_strlen($identificacao) > 200) {
                throw new RegraIntegracaoViolada('sem_identificacao', 'Descreva quem está sendo liberado ou a ocorrência (de 3 a 200 caracteres).');
            }
            if (mb_strlen($justificativa) < 20 || mb_strlen($justificativa) > 1000) {
                throw new RegraIntegracaoViolada('sem_justificativa', 'A liberação excepcional exige justificativa de 20 a 1000 caracteres.');
            }
        } else {
            if ($pessoa === null) {
                throw new RegraIntegracaoViolada('sem_pessoa', 'Selecione a pessoa. Sem cadastro, use a liberação excepcional.');
            }
            if (($restricoes = $this->restricoesDaPessoa($pessoa)) !== []) {
                throw new RegraIntegracaoViolada(array_key_first($restricoes), reset($restricoes).' Use a liberação excepcional, se for o caso.');
            }
        }

        $ponto = $equipamento->pontoVigente();
        $vinculo = $pessoa ? Vinculo::query()->where('pessoa_id', $pessoa->id)->whereNull('ended_at')->where('status', 'ativo')->first() : null;

        try {
            return DB::transaction(function () use ($operador, $posto, $equipamento, $ponto, $pessoa, $vinculo, $direcao, $motivo, $excepcional, $identificacao, $justificativa, $chaveIdempotencia): HistoricoAcesso {
                $evento = HistoricoAcesso::query()->create([
                    'pessoa_id' => $pessoa?->id,
                    'imovel_id' => $vinculo?->imovel_id,
                    'ponto_acesso' => $ponto->nome,
                    'tipo' => $direcao->value,
                    'resultado' => self::RESULTADO_DECISAO,
                    'origem' => ComandosAbertura::ORIGEM_LIBERACAO_MANUAL,
                    'excepcional' => $excepcional,
                    'identificacao_excepcional' => $identificacao,
                    'justificativa_excepcional' => $justificativa,
                    'motivo' => $motivo,
                    'posto_id' => $posto->id,
                    'ponto_acesso_id' => $ponto->id,
                    'equipamento_id' => $equipamento->id,
                    'operator_id' => $operador->id,
                    'protocol' => 'SRA-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
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

                $evento->forceFill([
                    'operacao_integracao_id' => $operacao->id,
                    'correlation_id' => $operacao->correlation_id,
                ])->save();

                $this->audit->record(
                    action: $excepcional ? 'liberacao_manual_excepcional_solicitada' : 'liberacao_manual_solicitada',
                    module: 'acessos',
                    entityType: 'historico_acessos',
                    entityId: $evento->id,
                    result: 'solicitado',
                    justification: $excepcional ? $motivo.' | Justificativa: '.$justificativa : $motivo,
                    classification: 'restrita',
                    metadata: [
                        'protocolo' => $evento->protocol,
                        'implantacao_id' => $equipamento->implantacao_id,
                        'posto_id' => $posto->id,
                        'posto' => $posto->nome,
                        'equipamento_id' => $equipamento->id,
                        'equipamento' => $equipamento->nome,
                        'ponto_acesso_id' => $ponto->id,
                        'ponto_acesso' => $ponto->nome,
                        'porta_rele' => $equipamento->porta_rele,
                        'direcao' => $direcao->value,
                        'pessoa_id' => $pessoa?->id,
                        'imovel_id' => $vinculo?->imovel_id,
                        'excepcional' => $excepcional,
                        'identificacao_excepcional' => $identificacao,
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

    public function operadorNoPosto(User $operador, Posto $posto): bool
    {
        return $posto->ativo() && OperadorPosto::query()
            ->where('user_id', $operador->id)
            ->where('posto_id', $posto->id)
            ->whereNull('ended_at')
            ->exists();
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
