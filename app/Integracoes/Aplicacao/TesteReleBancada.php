<?php

namespace App\Integracoes\Aplicacao;

use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\EstadoSaude;
use App\Integracoes\Dominio\Enums\OperacaoIntegracao;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\Equipamento;
use App\Models\EquipamentoCapacidade;
use App\Models\OperacaoIntegracao as Operacao;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Teste individual do relé de UMA facial, em bancada (ADR-016 §11 passo 8
 * e §21). Não é decisão de acesso e não registra entrada nem saída: o
 * resultado mais forte possível é o terminal ter aceitado o comando.
 *
 * O comando é sempre construído a partir do equipamento escolhido,
 * relido no banco dentro da implantação atual. IP, porta, certificado,
 * credencial e adaptador vêm só do cadastro desse equipamento; nada disso
 * é aceito do navegador.
 */
class TesteReleBancada
{
    /** Testes aceitos exigidos antes de registrar o relé como homologado (roteiro: testar e repetir). */
    public const TESTES_PARA_HOMOLOGAR = 2;

    public function __construct(private OutboxIntegracao $outbox, private AuditService $audit) {}

    /**
     * Motivos que impedem o teste agora, na ordem em que devem ser resolvidos.
     * Lista vazia = pode testar.
     *
     * @return array<string, string> código => explicação para o operador
     */
    public function bloqueios(Equipamento $equipamento, User $ator): array
    {
        $capacidade = EquipamentoCapacidade::query()
            ->where('equipamento_id', $equipamento->id)
            ->where('capacidade', Capacidade::AberturaRemota->value)
            ->first();

        $bloqueios = [
            'sem_permissao' => ! $ator->hasPermission(ComandosAbertura::PERMISSAO_LIBERAR)
                ? 'Você não tem a permissão equipamentos.liberar-acesso.' : null,
            'teste_rele_desabilitado' => ! config('integracoes.teste_rele_habilitado')
                ? 'Teste do relé desligado nesta instalação (SDV_INTEGRACAO_TESTE_RELE).' : null,
            'equipamento_fora_de_homologacao' => $equipamento->status !== StatusEquipamento::EmHomologacao
                ? 'O terminal não está em homologação.' : null,
            'sem_ponto' => $equipamento->pontoVigente() === null
                ? 'O terminal não está vinculado a um ponto de acesso.' : null,
            'equipamento_offline' => $equipamento->estado_saude !== EstadoSaude::Conectado
                ? 'O terminal não está online. Teste a conexão antes.' : null,
            'certificado_nao_confiavel' => $equipamento->esquema === 'https' && $equipamento->tls_pin_sha256 === null
                ? 'O certificado HTTPS do terminal ainda não foi confiado.' : null,
            'firmware_nao_homologado' => $capacidade === null
                || $equipamento->firmware_versao === null
                || $capacidade->firmware_versao !== $equipamento->firmware_versao
                || ! $capacidade->estado_homologacao?->executavel()
                ? 'O firmware atual não tem abertura remota homologada. Consulte as capacidades.' : null,
            'capacidade_ausente' => ! $equipamento->suporta(Capacidade::AberturaRemota)
                ? 'Abertura remota não confirmada para este terminal.' : null,
        ];

        return array_filter($bloqueios);
    }

    public function solicitar(Equipamento $equipamento, string $chaveIdempotencia, User $ator, string $motivo): Operacao
    {
        // Relido no banco: escopo da implantação atual e estado mais recente.
        $equipamento = Equipamento::query()->findOrFail($equipamento->id);

        if (($bloqueios = $this->bloqueios($equipamento, $ator)) !== []) {
            throw new RegraIntegracaoViolada(array_key_first($bloqueios), reset($bloqueios));
        }

        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 500) {
            throw new RegraIntegracaoViolada('sem_motivo', 'Informe o motivo do teste (de 5 a 500 caracteres).');
        }
        if (! preg_match('/^[A-Za-z0-9:_-]{8,120}$/', $chaveIdempotencia)) {
            throw new RegraIntegracaoViolada('chave_invalida', 'Reabra a confirmação e tente novamente.');
        }

        $ponto = $equipamento->pontoVigente();

        return DB::transaction(function () use ($equipamento, $ponto, $chaveIdempotencia, $ator, $motivo): Operacao {
            $operacao = $this->outbox->registrar(
                $equipamento,
                OperacaoIntegracao::AberturaRemota,
                [
                    'ponto_acesso_id' => $ponto->id,
                    'porta_rele' => $equipamento->porta_rele,
                    'decisao_referencia' => null,
                    'teste' => 'rele_bancada',
                ],
                $chaveIdempotencia,
                agregadoTipo: 'teste_rele_bancada',
                agregadoId: (string) Str::uuid7(),
                ator: $ator,
                expiraEm: now()->addSeconds((int) config('integracoes.abertura_validade_segundos')),
                origem: ComandosAbertura::ORIGEM_TESTE_BANCADA,
            );

            if ($operacao->wasRecentlyCreated) {
                $this->audit->record(
                    action: 'liberacao_remota_solicitada',
                    module: 'integracoes',
                    entityType: 'equipamentos',
                    entityId: $equipamento->id,
                    result: 'solicitado',
                    justification: $motivo,
                    classification: 'restrita',
                    metadata: [
                        'operacao_id' => $operacao->id,
                        'implantacao_id' => $equipamento->implantacao_id,
                        'equipamento_nome' => $equipamento->nome,
                        'ponto_acesso_id' => $ponto->id,
                        'ponto_codigo' => $ponto->codigo,
                        'porta_rele' => $equipamento->porta_rele,
                        'modo' => ComandosAbertura::ORIGEM_TESTE_BANCADA,
                        'chave_idempotencia' => $operacao->chave_idempotencia,
                        'correlation_id' => $operacao->correlation_id,
                        'ip_operador' => request()?->ip(),
                    ],
                    implantacaoId: $equipamento->implantacao_id,
                );
            }

            return $operacao;
        });
    }

    /** Testes de bancada do relé deste terminal que o terminal aceitou. */
    public function testesAceitos(Equipamento $equipamento): int
    {
        return Operacao::query()
            ->where('equipamento_id', $equipamento->id)
            ->where('operacao', OperacaoIntegracao::AberturaRemota->value)
            ->where('origem', ComandosAbertura::ORIGEM_TESTE_BANCADA)
            ->whereIn('resultado', [ResultadoEquipamento::Aceito->value, ResultadoEquipamento::Confirmado->value])
            ->count();
    }

    /**
     * Passo 9 do roteiro: o operador registra, para ESTA facial, que ouviu o
     * relé acionar nos testes. Exige os testes aceitos e a observação física.
     */
    public function registrarHomologacao(Equipamento $equipamento, User $ator, bool $acionamentoObservado, string $observacao): Equipamento
    {
        if (! $ator->hasPermission(ComandosAbertura::PERMISSAO_LIBERAR)) {
            throw new RegraIntegracaoViolada('sem_permissao', 'Você não tem a permissão equipamentos.liberar-acesso.');
        }
        if (! $acionamentoObservado) {
            throw new RegraIntegracaoViolada('acionamento_nao_observado', 'Registre a homologação somente se o clique do relé foi observado.');
        }
        if (mb_strlen(trim($observacao)) < 5) {
            throw new RegraIntegracaoViolada('sem_observacao', 'Descreva o que foi observado (de 5 a 500 caracteres).');
        }

        return DB::transaction(function () use ($equipamento, $ator, $observacao): Equipamento {
            $equipamento = Equipamento::query()->lockForUpdate()->findOrFail($equipamento->id);

            if ($this->testesAceitos($equipamento) < self::TESTES_PARA_HOMOLOGAR) {
                throw new RegraIntegracaoViolada('testes_insuficientes', 'Faça o teste e repita uma vez antes de registrar a homologação do relé.');
            }

            $equipamento->forceFill([
                'rele_homologado_em' => now(),
                'rele_homologado_por' => $ator->id,
                'versao' => $equipamento->versao + 1,
                'updated_by' => $ator->id,
            ])->save();

            $this->audit->record(
                action: 'equipamento_rele_homologado',
                module: 'integracoes',
                entityType: 'equipamentos',
                entityId: $equipamento->id,
                changes: ['rele_homologado_em' => ['old' => null, 'new' => $equipamento->rele_homologado_em]],
                justification: mb_substr(trim($observacao), 0, 500),
                metadata: [
                    'porta_rele' => $equipamento->porta_rele,
                    'firmware' => $equipamento->firmware_versao,
                    'testes_aceitos' => $this->testesAceitos($equipamento),
                ],
                implantacaoId: $equipamento->implantacao_id,
            );

            return $equipamento;
        });
    }
}
