<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Livewire\Component;

class ForgotPassword extends Component
{
    public string $identification = '';

    public bool $linkSent = false;

    public function sendResetLink(): void
    {
        $this->validate([
            'identification' => ['required', 'string'],
        ], [
            'identification.required' => 'Informe sua identificação.',
        ]);

        $user = User::query()->where('username', $this->identification)->first();

        if ($user !== null) {
            Password::sendResetLink(['email' => $user->email]);
        }

        // docs/008 §12.1 — não revelar existência de usuário: mesma resposta
        // independentemente de $user ter sido encontrado ou não.
        $this->reset('identification');
        $this->linkSent = true;
    }

    public function render(): View
    {
        return view('livewire.forgot-password')
            ->layout('components.layouts.guest', ['title' => 'Recuperar acesso']);
    }
}
