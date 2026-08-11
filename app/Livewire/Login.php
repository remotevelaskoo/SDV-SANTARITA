<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Login extends Component
{
    public string $identification = '';

    public string $password = '';

    public function useDemoAccount(): void
    {
        $this->identification = 'portaria';
        $this->password = 'sdv2026';
        $this->resetErrorBag();
    }

    public function login(): mixed
    {
        $credentials = $this->validate([
            'identification' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'identification.required' => 'Informe sua identificação.',
            'password.required' => 'Informe sua senha.',
        ]);

        if (! Auth::attempt(['username' => $credentials['identification'], 'password' => $credentials['password']])) {
            $this->addError('credentials', 'Identificação ou senha inválida.');

            return null;
        }

        session()->regenerate();

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
