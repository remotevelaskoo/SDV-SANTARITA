<?php

namespace App\Livewire;

use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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

    public function login(AuditService $audit): mixed
    {
        $credentials = $this->validate([
            'identification' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'identification.required' => 'Informe sua identificação.',
            'password.required' => 'Informe sua senha.',
        ]);

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
        session()->regenerate();

        $audit->record(
            action: 'entrou',
            module: 'autenticacao',
            entityType: 'sessao',
            entityId: hash('sha256', session()->getId()),
            classification: 'restrita',
        );

        return $this->redirectRoute('dashboard', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.login')
            ->layout('components.layouts.guest', [
                'title' => 'Entrar no sistema',
            ]);
    }
}
