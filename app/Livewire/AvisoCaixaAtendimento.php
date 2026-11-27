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
 * e não só no último passo, e já abre o caixa ali mesmo, confirmando o posto.
 * Sem abrir, a tela continua servindo para consultar e "Salvar sem liberar".
 */
class AvisoCaixaAtendimento extends Component
{
    private const MONEY_REGEX = '/^\d+([.,]\d{1,2})?$/';

    public string $saldoInicial = '';

    public string $postoAbertura = '';

    public bool $dispensado = false;

    public ?string $abertoNoPosto = null;

    public function mount(TurnosOperacionais $turnos): void
    {
        $sugerido = Configuracao::obter('caixa.saldo_sugerido_abertura');
        $this->saldoInicial = $sugerido !== null ? number_format((float) $sugerido, 2, ',', '') : '200,00';

        $usuario = Auth::user();
        $this->postoAbertura = $usuario instanceof User ? (string) $turnos->turnoAtivo($usuario)?->posto_id : '';
    }

    public function abrir(TurnosOperacionais $turnos): void
    {
        $operador = Auth::user();
        abort_unless($operador instanceof User && $operador->hasPermission('caixa.proprio.gerenciar'), 403);

        $this->validate([
            'postoAbertura' => ['required', 'string'],
            'saldoInicial' => ['required', 'regex:'.self::MONEY_REGEX],
        ], [
            'postoAbertura.required' => 'Escolha o posto em que você está trabalhando.',
            'saldoInicial.required' => 'Informe o saldo inicial para abrir o caixa.',
            'saldoInicial.regex' => 'Informe um valor numérico válido, ex.: 200,00.',
        ]);

        try {
            $caixa = $turnos->abrirCaixa($operador, (float) str_replace(',', '.', $this->saldoInicial), $this->postoAbertura);
        } catch (RegraIntegracaoViolada $e) {
            $this->addError('postoAbertura', $e->getMessage());

            return;
        }

        $this->abertoNoPosto = $caixa->terminal;
        // O atendimento recalcula o botão "Liberar entrada"; o layout arma o aviso de fechar o navegador.
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
            'mostrar' => $operaCaixa && ($this->abertoNoPosto !== null || (! $this->dispensado && $turnos->caixaAberto($operador) === null)),
            'postos' => $operaCaixa ? $turnos->postosParaAbrirCaixa($operador) : collect(),
            'postoAtual' => $operaCaixa ? $turnos->turnoAtivo($operador)?->posto_id : null,
        ]);
    }
}
