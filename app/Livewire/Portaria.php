<?php

namespace App\Livewire;

use App\Models\CaixaTurno;
use App\Models\HistoricoAcesso;
use App\Models\PreRegistration;
use App\Models\Vinculo;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Portaria extends Component
{
    /**
     * Atalhos provisórios do Modo Portaria — aguardando confirmação da equipe
     * (ver docs/013_PLANO_DE_DIVISAO_DO_DESENVOLVIMENTO.md, seção 8).
     * "Cadastro rápido" segue sem rota própria: o fluxo real vive embutido em
     * AccessValidation (P06), não existe como tela independente.
     *
     * @var list<array{label: string, description: string, icon: string, route: string|null}>
     */
    public array $shortcuts = [
        ['label' => 'Validar entrada', 'description' => 'Identificar pessoa ou veículo e liberar acesso', 'icon' => 'shield', 'route' => 'validation'],
        ['label' => 'Pré-cadastros pendentes', 'description' => 'Analisar solicitações antecipadas de visitantes', 'icon' => 'badge-check', 'route' => 'pre-registrations'],
        ['label' => 'Cadastro rápido', 'description' => 'Criar cadastro mínimo durante o atendimento', 'icon' => 'users', 'route' => null],
        ['label' => 'Consultar caixa', 'description' => 'Ver movimentações e conferir o turno atual', 'icon' => 'wallet', 'route' => 'cash-register'],
    ];

    /** @return list<array{severity: string, title: string, description: string}> */
    public function alerts(): array
    {
        $pendentes = PreRegistration::query()
            ->where('status', 'aguardando')
            ->where('submitted_at', '<=', now()->subDay())
            ->count();

        if ($pendentes === 0) {
            return [];
        }

        return [[
            'severity' => 'warning',
            'title' => $pendentes.($pendentes === 1 ? ' pré-cadastro aguardando análise há mais de 24 horas' : ' pré-cadastros aguardando análise há mais de 24 horas'),
            'description' => 'Solicitações de visitantes pendentes de decisão da portaria.',
        ]];
    }

    /** @return array{status: string, code: string, openedAt: string, openedBy: string, total: float} */
    public function cashRegister(): array
    {
        $turno = CaixaTurno::query()->where('status', 'aberto')->latest('opened_at')->with('operator')->first();

        if (! $turno) {
            return [
                'status' => 'fechado',
                'code' => 'Nenhum caixa aberto',
                'openedAt' => '—',
                'openedBy' => '—',
                'total' => 0.0,
            ];
        }

        return [
            'status' => 'aberto',
            'code' => $turno->terminal,
            'openedAt' => $turno->opened_at->format('H:i'),
            'openedBy' => $turno->operator?->name ?? 'Sistema',
            'total' => $turno->expectedBalance(),
        ];
    }

    /** @return list<array{time: string, name: string, relation: string, subject: string, result: string}> */
    public function recentAttendances(): array
    {
        return HistoricoAcesso::query()
            ->with('pessoa')
            ->orderByDesc('occurred_at')
            ->limit(5)
            ->get()
            ->map(function (HistoricoAcesso $entry) {
                $vinculo = $entry->pessoa
                    ? Vinculo::query()->where('pessoa_id', $entry->pessoa->id)->whereNull('ended_at')->first()
                    : null;

                return [
                    'time' => $entry->occurred_at->format('H:i'),
                    'name' => $entry->pessoa?->nomeExibicao() ?? 'Não identificado',
                    'relation' => $this->relationLabel($vinculo?->tipo),
                    'subject' => 'Validação de '.($entry->tipo === 'entrada' ? 'entrada' : 'saída').' — '.$entry->ponto_acesso,
                    'result' => $entry->resultado,
                ];
            })->values()->all();
    }

    private function relationLabel(?string $tipo): string
    {
        return match ($tipo) {
            'proprietario' => 'Proprietário',
            'inquilino' => 'Inquilino',
            'morador' => 'Morador',
            'prestador' => 'Prestador',
            'visitante' => 'Visitante',
            default => 'Visitante',
        };
    }

    public function render(): View
    {
        return view('livewire.portaria', [
            'alerts' => $this->alerts(),
            'cashRegister' => $this->cashRegister(),
            'recentAttendances' => $this->recentAttendances(),
        ])->layout('components.layouts.app', [
            'title' => 'Modo Portaria',
            'heading' => 'Modo Portaria',
            'headingDescription' => 'Operador de portaria · Portaria Principal',
        ]);
    }
}
