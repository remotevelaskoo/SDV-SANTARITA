<?php

namespace App\Support;

use App\Models\CaixaTurno;
use App\Models\PreRegistration;
use App\Models\User;

/**
 * Dados reais do cabeçalho do SDV One.
 *
 * Não mostra informações financeiras de outras pessoas ao operador comum
 * e não anuncia pré-cadastros para perfis sem permissão de análise.
 * Todas as consultas usam o escopo de implantação já aplicado nos modelos.
 */
final class OperationalHeaderState
{
    /**
     * @return array{cash: ?array{label: string, open: bool}, pendingPreRegistrations: ?int}
     */
    public static function forUser(?User $user): array
    {
        if ($user === null) {
            return ['cash' => null, 'pendingPreRegistrations' => null];
        }

        $ownCash = $user->hasPermission('caixa.proprio.gerenciar');
        $consolidatedCash = $user->hasPermission('caixa.consolidado.consultar');
        $cash = null;

        if ($ownCash || $consolidatedCash) {
            $turnos = CaixaTurno::query()->where('status', 'aberto');

            if (! $consolidatedCash) {
                $turnos->where('operator_id', $user->getKey());
            }

            $turno = $turnos->orderByDesc('opened_at')->first();

            $cash = [
                'label' => $turno
                    ? $turno->terminal.' aberto às '.$turno->opened_at->format('H:i')
                    : ($consolidatedCash ? 'Nenhum caixa aberto' : 'Seu caixa está fechado'),
                'open' => $turno !== null,
            ];
        }

        return [
            'cash' => $cash,
            'pendingPreRegistrations' => $user->hasPermission('pre-registro.analisar')
                ? PreRegistration::query()->where('status', 'aguardando')->count()
                : null,
        ];
    }
}
