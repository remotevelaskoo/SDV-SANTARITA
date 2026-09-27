<?php

namespace App\Integracoes\Aplicacao;

use App\Integracoes\Dominio\Dados\CredencialParaSincronizar;
use App\Integracoes\Dominio\Enums\EstadoSincronizacao;
use App\Integracoes\Dominio\Enums\OperacaoIntegracao;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\CredencialSincronizacao;
use App\Models\Equipamento;
use App\Models\OperacaoIntegracao as Operacao;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * Pede a distribuição ou a revogação de uma credencial num terminal.
 *
 * Quem chama já decidiu, no SDV, que a credencial é elegível (RN-040): o
 * reconhecimento facial nunca cria autorização. Credencial facial é
 * recusada enquanto o ADR-013 estiver adiado.
 */
class SincronizacaoCredenciais
{
    public function __construct(private OutboxIntegracao $outbox, private AuditService $audit) {}

    public function solicitarSincronizacao(Equipamento $equipamento, CredencialParaSincronizar $credencial, string $chaveIdempotencia, ?User $ator = null): Operacao
    {
        if ($credencial->ehFacial()) {
            throw new RegraIntegracaoViolada('biometria_bloqueada_adr013', 'Credencial facial não pode ser sincronizada enquanto o ADR-013 não for retomado e aprovado.');
        }
        if ($credencial->vigenciaFim !== null && $credencial->vigenciaFim <= now()->toDateTimeImmutable()) {
            throw new RegraIntegracaoViolada('credencial_expirada', 'Credencial com vigência encerrada não é enviada ao terminal.');
        }
        $this->garantirAtivo($equipamento);

        return DB::transaction(function () use ($equipamento, $credencial, $chaveIdempotencia, $ator): Operacao {
            $operacao = $this->outbox->registrar(
                $equipamento,
                OperacaoIntegracao::SincronizarCredencial,
                [
                    'credencial_id' => $credencial->credencialId,
                    'tipo' => $credencial->tipo,
                    'titular_referencia' => $credencial->titularReferencia,
                    'nome_exibicao' => $credencial->nomeExibicao,
                    'vigencia_inicio' => $credencial->vigenciaInicio->format(DATE_ATOM),
                    'vigencia_fim' => $credencial->vigenciaFim?->format(DATE_ATOM),
                    'direcao' => $credencial->direcao->value,
                ],
                $chaveIdempotencia,
                agregadoTipo: 'credencial',
                agregadoId: $credencial->credencialId,
                ator: $ator,
            );

            $this->marcar($equipamento, $credencial->credencialId, EstadoSincronizacao::Aguardando, $operacao);
            $this->auditar('credencial_sincronizacao_solicitada', $equipamento, $credencial->credencialId, $operacao);

            return $operacao;
        });
    }

    public function solicitarRevogacao(Equipamento $equipamento, string $credencialId, string $motivo, string $chaveIdempotencia, ?User $ator = null): Operacao
    {
        // Revogação vale mesmo com o terminal em manutenção: a pendência fica
        // registrada e visível até ser confirmada.
        if ($equipamento->status === StatusEquipamento::Inativo) {
            throw new RegraIntegracaoViolada('equipamento_inativo', 'Equipamento inativo não recebe operações.');
        }

        return DB::transaction(function () use ($equipamento, $credencialId, $motivo, $chaveIdempotencia, $ator): Operacao {
            $operacao = $this->outbox->registrar(
                $equipamento,
                OperacaoIntegracao::RevogarCredencial,
                ['credencial_id' => $credencialId, 'motivo' => mb_substr($motivo, 0, 200)],
                $chaveIdempotencia,
                agregadoTipo: 'credencial',
                agregadoId: $credencialId,
                ator: $ator,
            );

            $this->marcar($equipamento, $credencialId, EstadoSincronizacao::AtualizacaoPendente, $operacao);
            $this->auditar('credencial_revogacao_solicitada', $equipamento, $credencialId, $operacao, $motivo);

            return $operacao;
        });
    }

    private function garantirAtivo(Equipamento $equipamento): void
    {
        if ($equipamento->status !== StatusEquipamento::Ativo) {
            throw new RegraIntegracaoViolada('equipamento_nao_ativo', 'Somente terminais ativos recebem credenciais.');
        }
        if ($equipamento->vinculoVigente === null) {
            throw new RegraIntegracaoViolada('sem_ponto', 'O terminal não está vinculado a um ponto de acesso.');
        }
    }

    private function marcar(Equipamento $equipamento, string $credencialId, EstadoSincronizacao $estado, Operacao $operacao): void
    {
        $sincronizacao = CredencialSincronizacao::query()->firstOrNew([
            'equipamento_id' => $equipamento->id,
            'credencial_id' => $credencialId,
        ]);

        if ($sincronizacao->ultima_operacao_id === $operacao->id) {
            return; // repetição idempotente da mesma solicitação
        }

        $sincronizacao->forceFill([
            'estado' => $estado,
            'ultima_operacao_id' => $operacao->id,
            'versao' => ($sincronizacao->versao ?? 0) + 1,
        ])->save();
    }

    private function auditar(string $acao, Equipamento $equipamento, string $credencialId, Operacao $operacao, ?string $motivo = null): void
    {
        $this->audit->record(
            action: $acao,
            module: 'integracoes',
            entityType: 'equipamentos',
            entityId: $equipamento->id,
            justification: $motivo,
            metadata: ['credencial_id' => $credencialId, 'operacao_id' => $operacao->id, 'correlation_id' => $operacao->correlation_id],
        );
    }
}
