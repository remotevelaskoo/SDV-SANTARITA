<?php

namespace App\Livewire;

use App\Integracoes\Aplicacao\TurnosOperacionais;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Login extends Component
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public string $identification = '';

    public string $password = '';

    public function useDemoAccount(): void
    {
        $this->identification = 'portaria';
        $this->password = 'sdv2026';
        $this->resetErrorBag();
    }

    public function login(AuditService $audit, TurnosOperacionais $turnos): mixed
    {
        try {
            $credentials = $this->validate([
                'identification' => ['required', 'string'],
                'password' => ['required', 'string'],
            ], [
                'identification.required' => 'Informe sua identificação.',
                'password.required' => 'Informe sua senha.',
            ]);
        } catch (ValidationException $exception) {
            $this->reset('password');

            throw $exception;
        }

        // Chave combina identificação + IP: freia tanto quem tenta várias
        // senhas contra uma conta quanto um IP tentando várias contas, sem
        // travar globalmente outros operadores legítimos no mesmo terminal.
        $throttleKey = Str::lower($credentials['identification']).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $audit->record(
                action: 'tentou_entrar',
                module: 'autenticacao',
                entityType: 'sessao',
                result: 'negado',
                reasonCode: 'limite_tentativas_excedido',
                classification: 'restrita',
            );
            $this->reset('password');
            $this->addError('credentials', 'Muitas tentativas. Tente novamente em '.RateLimiter::availableIn($throttleKey).' segundos.');

            return null;
        }

        if (! Auth::attempt(['username' => $credentials['identification'], 'password' => $credentials['password'], 'status' => 'ativo'])) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            $audit->record(
                action: 'tentou_entrar',
                module: 'autenticacao',
                entityType: 'sessao',
                result: 'negado',
                reasonCode: 'credenciais_invalidas',
                classification: 'restrita',
            );
            $this->reset('password');
            $this->addError('credentials', 'Identificação ou senha inválida.');

            return null;
        }

        RateLimiter::clear($throttleKey);
        $this->reset('password');
        session()->regenerate();
        // Referência para derrubar a sessão se o posto for liberado depois
        // (EncerrarSessaoDePostoLiberado).
        session(['autenticado_em' => now()->getTimestamp()]);

        $audit->record(
            action: 'entrou',
            module: 'autenticacao',
            entityType: 'sessao',
            entityId: hash('sha256', session()->getId()),
            classification: 'restrita',
        );

        // Regra do PO (26/11/2026): quem opera posto confirma ou escolhe o posto
        // logo ao entrar. Turno com caixa aberto continua (o sistema caiu ou o
        // computador desligou); turno antigo com caixa fechado é encerrado.
        $usuario = Auth::user();
        if ($usuario instanceof User && SelecaoPosto::operaPosto($usuario) && $turnos->postosAtivos()->isNotEmpty()) {
            $turnos->retomarAoEntrar($usuario);
            session([SelecaoPosto::SESSAO_CONFIRMAR_POSTO => true]);

            return $this->redirectRoute('posto.selecionar', ['voltar' => '/inicio'], navigate: false);
        }

        return $this->redirectRoute('home', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.login')
            ->layout('components.layouts.guest', [
                'title' => 'Entrar no sistema',
            ]);
    }
}
