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
 * Escolha do posto do turno (SDV-OPE-018 §2 e §14). Qualquer operador assume
 * qualquer posto livre; um posto ocupado mostra quem está nele. Trocar de
 * posto ou encerrar o turno exige o caixa fechado; tudo é auditado.
 */
class SelecaoPosto extends Component
{
    /** Supervisor que preferiu usar o Modo Portaria sem ocupar um posto. */
    public const SESSAO_SEM_POSTO = 'posto.seguiu_sem_posto';

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

        session()->forget(self::SESSAO_SEM_POSTO);

        return $this->redirect($this->destino(), navigate: false);
    }

    public function encerrar(TurnosOperacionais $turnos): void
    {
        try {
            $turnos->encerrar($this->operador());
            $this->erro = null;
        } catch (RegraIntegracaoViolada $e) {
            $this->erro = $e->getMessage();
        }
    }

    /** Só para quem supervisiona (caixa consolidado): entra no Modo Portaria sem ocupar um posto. */
    public function seguirSemPosto(): mixed
    {
        abort_unless($this->operador()->hasPermission('caixa.consolidado.consultar'), 403);
        session()->put(self::SESSAO_SEM_POSTO, true);

        return $this->redirect($this->destino(), navigate: false);
    }

    public function render(TurnosOperacionais $turnos): View
    {
        $operador = $this->operador();

        return view('livewire.selecao-posto', [
            'postos' => $turnos->postosAtivos(),
            'ocupacoes' => $turnos->ocupacoes(),
            'turno' => $turnos->turnoAtivo($operador),
            'caixaAberto' => $turnos->caixaAberto($operador) !== null,
            'podeSeguirSemPosto' => $operador->hasPermission('caixa.consolidado.consultar'),
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
