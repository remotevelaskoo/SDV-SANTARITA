<?php

namespace App\Livewire;

use App\Models\CaixaTurno;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CaixasOperador;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Histórico de fechamentos (AJ-008, SDV-UXR-014 §5.6): Gestor, Auditor e
 * Administrador veem todos os da implantação; Porteiro/Caixa, só os próprios.
 */
class CaixaFechamentos extends Component
{
    public string $operador = '';

    public function mount(AuditService $audit): void
    {
        if ($this->consolidado()) {
            $audit->record(action: 'consultou_fechamentos_consolidados', module: 'caixa', entityType: 'caixa_turnos');
        }
    }

    private function consolidado(): bool
    {
        return $this->usuario()->hasPermission(CaixasOperador::PERMISSAO_CONSOLIDADO);
    }

    private function usuario(): User
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof User, 403);

        return $usuario;
    }

    public function render(): View
    {
        $usuario = $this->usuario();
        $consolidado = $this->consolidado();

        $fechamentos = CaixaTurno::query()
            ->where('status', 'fechado')
            ->when(! $consolidado, fn ($q) => $q->where('operator_id', $usuario->id))
            ->when($consolidado && $this->operador !== '', fn ($q) => $q->where('operator_id', (int) $this->operador))
            ->with(['operator', 'posto'])
            ->latest('closed_at')
            ->limit(200)
            ->get();

        return view('livewire.caixa-fechamentos', [
            'fechamentos' => $fechamentos,
            'consolidado' => $consolidado,
            'operadores' => $consolidado
                ? User::query()->whereIn('id', CaixaTurno::query()->where('status', 'fechado')->select('operator_id'))->orderBy('name')->get(['id', 'name'])
                : collect(),
        ])->layout('components.layouts.app', [
            'title' => 'Fechamentos de caixa',
            'heading' => 'Fechamentos de caixa',
            'headingDescription' => $consolidado ? 'Todos os turnos fechados da implantação' : 'Seus turnos fechados',
        ]);
    }
}
