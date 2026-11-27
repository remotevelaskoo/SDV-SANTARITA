<?php

namespace App\Livewire;

use App\Integracoes\Aplicacao\TurnosOperacionais;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\Configuracao;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Aviso no topo do atendimento (regra do PO, 26/11/2026): liberar exige o
 * caixa aberto (PO 14/11), então quem opera caixa fica sabendo logo ao entrar,
 * e não só no último passo, e já abre o caixa ali mesmo. Sem abrir, a tela
 * continua servindo para consultar e "Salvar sem liberar".
 */
class AvisoCaixaAtendimento extends Component
{
    private const MONEY_REGEX = '/^\d+([.,]\d{1,2})?$/';

    public string $saldoInicial = '';

    public bool $dispensado = false;

    public bool $acabouDeAbrir = false;

    public function mount(): void
    {
        $sugerido = Configuracao::obter('caixa.saldo_sugerido_abertura');
        $this->saldoInicial = $sugerido !== null ? number_format((float) $sugerido, 2, ',', '') : '200,00';
    }

    public function abrir(TurnosOperacionais $turnos): void
    {
        $operador = $this->operador();
        abort_unless($operador->hasPermission('caixa.proprio.gerenciar'), 403);

        $this->validate([
            'saldoInicial' => ['required', 'regex:'.self::MONEY_REGEX],
        ], [
            'required' => 'Informe o saldo inicial para abrir o caixa.',
            'regex' => 'Informe um valor numérico válido, ex.: 200,00.',
        ]);

        try {
            $turnos->abrirCaixa($operador, (float) str_replace(',', '.', $this->saldoInicial));
        } catch (RegraIntegracaoViolada $e) {
            $this->addError('saldoInicial', $e->getMessage());

            return;
        }

        $this->acabouDeAbrir = true;
        // O atendimento recalcula o botão "Liberar entrada".
        $this->dispatch('caixa-aberto');
        $this->js("document.getElementById('sdv-caixa-proprio')?.setAttribute('data-aberto', '1')");
    }

    public function dispensar(): void
    {
        $this->dispensado = true;
    }

    public function render(TurnosOperacionais $turnos): View
    {
        // Sem usuário (a tela de atendimento trata o acesso), o aviso só não aparece.
        $operador = Auth::user();
        $operaCaixa = $operador instanceof User && $operador->hasPermission('caixa.proprio.gerenciar');

        return view('livewire.aviso-caixa-atendimento', [
            'mostrar' => $operaCaixa && ($this->acabouDeAbrir || (! $this->dispensado && $turnos->caixaAberto($operador) === null)),
            'posto' => $operaCaixa ? $turnos->turnoAtivo($operador)?->posto : null,
        ]);
    }

    private function operador(): User
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof User, 403);

        return $usuario;
    }
}
