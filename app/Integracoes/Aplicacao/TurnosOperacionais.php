<?php

namespace App\Integracoes\Aplicacao;

use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\CaixaTurno;
use App\Models\OperadorTurno;
use App\Models\Posto;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Posto assumido pelo operador no turno (SDV-OPE-018 §2 e §14).
 *
 * Regras do PO de 12/11/2026:
 * - qualquer operador pode assumir qualquer posto ativo (sem designação);
 * - um posto fica com um operador por vez; o próximo só entra depois que o
 *   atual fechar o caixa e encerrar o turno;
 * - com o caixa aberto não se troca de posto nem se encerra o turno;
 * - Gestor/Administrador (`postos.liberar`) liberam um posto preso, com
 *   motivo; o caixa do posto é fechado como "aguardando conferência".
 */
class TurnosOperacionais
{
    public const MOTIVO_TROCA = 'troca_de_posto';

    public const MOTIVO_ENCERRAMENTO = 'encerramento';

    public const MOTIVO_SAIDA = 'saida_do_sistema';

    public const MOTIVO_LIBERADO = 'liberado_pelo_gestor';

    public const PERMISSAO_LIBERAR = 'postos.liberar';

    public const CAIXA_AGUARDANDO_CONFERENCIA = 'aguardando_conferencia';

    public function __construct(private AuditService $audit) {}

    /** @return Collection<int, Posto> postos ativos da implantação; todos podem ser assumidos por qualquer operador */
    public function postosAtivos(): Collection
    {
        return Posto::query()
            ->where('status', 'ativo')
            ->orderByRaw("case tipo when 'principal' then 0 when 'veiculos' then 1 when 'pedestres' then 2 else 3 end")
            ->orderBy('nome')
            ->get();
    }

    /** @return Collection<string, OperadorTurno> turno aberto de cada posto ocupado, pela chave posto_id */
    public function ocupacoes(): Collection
    {
        return OperadorTurno::query()->whereNull('encerrado_em')->with('user')->get()->keyBy('posto_id');
    }

    /** Turno ativo do operador na implantação atual, se o posto ainda estiver ativo. */
    public function turnoAtivo(User $operador): ?OperadorTurno
    {
        $turno = OperadorTurno::query()->where('user_id', $operador->id)->whereNull('encerrado_em')->with('posto')->first();

        // Posto inativado: o turno deixa de valer.
        if ($turno !== null && $turno->posto?->ativo() !== true) {
            $this->fecharTurno($turno, 'posto_inativado');

            return null;
        }

        return $turno;
    }

    /** Caixa aberto do operador (um por operador; do posto dele quando aberto depois da regra). */
    public function caixaAberto(User $operador): ?CaixaTurno
    {
        return CaixaTurno::query()->where('operator_id', $operador->id)->where('status', 'aberto')->latest('opened_at')->first();
    }

    public function assumir(User $operador, string $postoId): OperadorTurno
    {
        $posto = $this->postosAtivos()->firstWhere('id', $postoId)
            ?? throw new RegraIntegracaoViolada('posto_indisponivel', 'Este posto não existe ou está desativado.');

        try {
            return DB::transaction(function () use ($operador, $posto): OperadorTurno {
                $anterior = OperadorTurno::query()->where('user_id', $operador->id)->whereNull('encerrado_em')->lockForUpdate()->first();
                if ($anterior?->posto_id === $posto->id) {
                    return $anterior->load('posto');
                }

                $ocupante = OperadorTurno::query()->where('posto_id', $posto->id)->whereNull('encerrado_em')->with('user')->lockForUpdate()->first();
                if ($ocupante !== null) {
                    throw $this->postoOcupado($posto, $ocupante);
                }

                if ($anterior !== null && $this->caixaAberto($operador) !== null) {
                    throw new RegraIntegracaoViolada('caixa_aberto', 'Feche o seu caixa antes de trocar de posto.');
                }

                $anterior?->forceFill(['encerrado_em' => now(), 'motivo_encerramento' => self::MOTIVO_TROCA])->save();

                $turno = OperadorTurno::query()->create([
                    'user_id' => $operador->id,
                    'posto_id' => $posto->id,
                    'turno_anterior_id' => $anterior?->id,
                    'iniciado_em' => now(),
                ]);

                $this->audit->record(
                    action: $anterior ? 'posto_operacional_trocado' : 'posto_operacional_assumido',
                    module: 'acessos',
                    entityType: 'operador_turnos',
                    entityId: $turno->id,
                    changes: ['posto_id' => ['old' => $anterior?->posto_id, 'new' => $posto->id]],
                    metadata: [
                        'posto' => $posto->nome,
                        'posto_anterior_id' => $anterior?->posto_id,
                        'turno_anterior_id' => $anterior?->id,
                        'operador_id' => $operador->id,
                    ],
                );

                return $turno->load('posto');
            });
        } catch (UniqueConstraintViolationException) {
            // Outro operador assumiu o mesmo posto no mesmo instante.
            $ocupante = OperadorTurno::query()->where('posto_id', $posto->id)->whereNull('encerrado_em')->with('user')->first();

            throw $ocupante ? $this->postoOcupado($posto, $ocupante) : new RegraIntegracaoViolada('posto_ocupado', 'Este posto acabou de ser assumido. Tente novamente.');
        }
    }

    /** Encerra o turno do operador e libera o posto. Com o caixa aberto, recusa. */
    public function encerrar(User $operador, string $motivo = self::MOTIVO_ENCERRAMENTO): void
    {
        $turno = OperadorTurno::query()->where('user_id', $operador->id)->whereNull('encerrado_em')->first();
        if ($turno === null) {
            return;
        }
        if ($this->caixaAberto($operador) !== null) {
            throw new RegraIntegracaoViolada('caixa_aberto', 'Feche o caixa antes de encerrar o turno e liberar o posto.');
        }

        $this->fecharTurno($turno, $motivo);
    }

    /**
     * Liberação por Gestor/Administrador de um posto preso. Fecha o caixa
     * aberto do operador como "aguardando conferência" (sem valor informado)
     * e encerra o turno; tudo auditado com o motivo.
     */
    public function liberar(Posto $posto, User $ator, string $motivo): void
    {
        if (! $ator->hasPermission(self::PERMISSAO_LIBERAR)) {
            throw new RegraIntegracaoViolada('sem_permissao', 'Só Gestor ou Administrador podem liberar um posto.');
        }
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 5) {
            throw new RegraIntegracaoViolada('motivo_obrigatorio', 'Informe o motivo da liberação (mínimo de 5 caracteres).');
        }

        DB::transaction(function () use ($posto, $ator, $motivo): void {
            $turno = OperadorTurno::query()->where('posto_id', $posto->id)->whereNull('encerrado_em')->with('user')->lockForUpdate()->first()
                ?? throw new RegraIntegracaoViolada('posto_livre', 'Este posto já está livre.');

            $caixa = CaixaTurno::query()->where('operator_id', $turno->user_id)->where('status', 'aberto')->lockForUpdate()->first();
            if ($caixa !== null) {
                $caixa->forceFill([
                    'status' => 'fechado',
                    'closed_at' => now(),
                    'expected_amount' => $caixa->expectedBalance(),
                    'informed_amount' => null,
                    'difference' => null,
                    'closing_status' => self::CAIXA_AGUARDANDO_CONFERENCIA,
                    'closing_notes' => "Posto liberado por {$ator->name}: {$motivo}",
                ])->save();
            }

            $turno->forceFill(['encerrado_em' => now(), 'motivo_encerramento' => self::MOTIVO_LIBERADO])->save();

            $this->audit->record(
                action: 'posto_operacional_liberado',
                module: 'acessos',
                entityType: 'operador_turnos',
                entityId: $turno->id,
                metadata: [
                    'posto_id' => $posto->id,
                    'posto' => $posto->nome,
                    'operador_id' => $turno->user_id,
                    'operador' => $turno->user?->name,
                    'liberado_por' => $ator->id,
                    'motivo' => $motivo,
                    'caixa_turno_id' => $caixa?->id,
                    'caixa_esperado' => $caixa?->expected_amount,
                ],
            );
        });
    }

    private function fecharTurno(OperadorTurno $turno, string $motivo): void
    {
        $turno->forceFill(['encerrado_em' => now(), 'motivo_encerramento' => $motivo])->save();
        $this->audit->record(
            action: 'posto_operacional_encerrado',
            module: 'acessos',
            entityType: 'operador_turnos',
            entityId: $turno->id,
            metadata: ['posto_id' => $turno->posto_id, 'motivo' => $motivo, 'operador_id' => $turno->user_id],
        );
    }

    private function postoOcupado(Posto $posto, OperadorTurno $ocupante): RegraIntegracaoViolada
    {
        $nome = $ocupante->user?->name ?? 'outro operador';

        return new RegraIntegracaoViolada(
            'posto_ocupado',
            "{$posto->nome} está com {$nome} desde {$ocupante->iniciado_em->format('H:i')}. Para entrar, {$nome} precisa fechar o caixa e encerrar o turno.",
        );
    }
}
