<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
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

        if ($credentials['identification'] !== 'portaria' || $credentials['password'] !== 'sdv2026') {
            $this->addError('credentials', 'Identificação ou senha inválida.');

            return null;
        }

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
