<?php

namespace App\Livewire;

use App\Integracoes\Aplicacao\TurnosOperacionais;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Escolha do posto do turno (SDV-OPE-018 §2). O operador assume um posto em
 * que está designado; trocar de posto encerra o anterior; tudo é auditado.
 */
class SelecaoPosto extends Component
{
    #[Url(as: 'voltar')]
    public string $voltar = '';

    public ?string $erro = null;

    public function assumir(string $postoId, TurnosOperacionais $turnos): mixed
    {
        try {
            $turnos->assumir($this->operador(), $postoId);
        } catch (RegraIntegracaoViolada $e) {
            $this->erro = $e->getMessage();

            return null;
        }

        return $this->redirect($this->destino(), navigate: false);
    }

    public function encerrar(TurnosOperacionais $turnos): void
    {
        $turnos->encerrar($this->operador());
    }

    public function render(TurnosOperacionais $turnos): View
    {
        $operador = $this->operador();

        return view('livewire.selecao-posto', [
            'postos' => $turnos->postosAutorizados($operador),
            'turno' => $turnos->turnoAtivo($operador),
        ])->layout('components.layouts.app', [
            'title' => 'Posto de trabalho',
            'heading' => 'Posto de trabalho',
            'headingDescription' => 'Escolha o posto em que você vai operar neste turno',
        ]);
    }

    private function destino(): string
    {
        // Só caminhos internos, para não virar redirecionamento aberto.
        return str_starts_with($this->voltar, '/') && ! str_starts_with($this->voltar, '//') ? $this->voltar : route('portaria');
    }

    private function operador(): User
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof User, 403);

        return $usuario;
    }
}
