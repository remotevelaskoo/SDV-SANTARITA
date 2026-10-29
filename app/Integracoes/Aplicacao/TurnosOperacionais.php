<?php

namespace App\Integracoes\Aplicacao;

use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\OperadorPosto;
use App\Models\OperadorTurno;
use App\Models\Posto;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Posto assumido pelo operador no turno (SDV-OPE-018 §2): o operador escolhe
 * um posto em que está designado; o sistema registra início, término e troca
 * de posto e mantém o posto ativo visível.
 */
class TurnosOperacionais
{
    public const MOTIVO_TROCA = 'troca_de_posto';

    public const MOTIVO_ENCERRAMENTO = 'encerramento';

    public const MOTIVO_SAIDA = 'saida_do_sistema';

    public function __construct(private AuditService $audit) {}

    /** @return Collection<int, Posto> postos ativos em que o operador está designado */
    public function postosAutorizados(User $operador): Collection
    {
        return Posto::query()
            ->where('status', 'ativo')
            ->whereIn('id', OperadorPosto::query()->where('user_id', $operador->id)->whereNull('ended_at')->select('posto_id'))
            ->orderByRaw("case tipo when 'principal' then 0 when 'veiculos' then 1 when 'pedestres' then 2 else 3 end")
            ->orderBy('nome')
            ->get();
    }

    /** Turno ativo do operador na implantação atual, se o posto ainda for válido para ele. */
    public function turnoAtivo(User $operador): ?OperadorTurno
    {
        $turno = OperadorTurno::query()->where('user_id', $operador->id)->whereNull('encerrado_em')->with('posto')->first();

        // Designação encerrada ou posto inativado: o turno deixa de valer.
        if ($turno !== null && ! $this->postosAutorizados($operador)->contains('id', $turno->posto_id)) {
            $this->encerrar($operador, 'designacao_encerrada');

            return null;
        }

        return $turno;
    }

    public function assumir(User $operador, string $postoId): OperadorTurno
    {
        $posto = $this->postosAutorizados($operador)->firstWhere('id', $postoId)
            ?? throw new RegraIntegracaoViolada('posto_nao_autorizado', 'Você não está designado para este posto.');

        return DB::transaction(function () use ($operador, $posto): OperadorTurno {
            $anterior = OperadorTurno::query()->where('user_id', $operador->id)->whereNull('encerrado_em')->lockForUpdate()->first();
            if ($anterior?->posto_id === $posto->id) {
                return $anterior;
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
    }

    public function encerrar(User $operador, string $motivo = self::MOTIVO_ENCERRAMENTO): void
    {
        $turno = OperadorTurno::query()->where('user_id', $operador->id)->whereNull('encerrado_em')->first();
        if ($turno === null) {
            return;
        }

        $turno->forceFill(['encerrado_em' => now(), 'motivo_encerramento' => $motivo])->save();
        $this->audit->record(
            action: 'posto_operacional_encerrado',
            module: 'acessos',
            entityType: 'operador_turnos',
            entityId: $turno->id,
            metadata: ['posto_id' => $turno->posto_id, 'motivo' => $motivo, 'operador_id' => $operador->id],
        );
    }
}
