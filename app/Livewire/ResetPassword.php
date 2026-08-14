<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Component;

class ResetPassword extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $completed = false;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function resetPassword(): void
    {
        $credentials = $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], [
            'email.required' => 'Link inválido: e-mail ausente.',
            'password.required' => 'Informe a nova senha.',
            'password.confirmed' => 'A confirmação não corresponde à nova senha.',
        ]);

        $status = Password::reset(
            [...$credentials, 'token' => $this->token],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'status' => $user->status === 'pendente' ? 'ativo' : $user->status,
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->reset('password', 'password_confirmation');
            $this->addError('token', 'Link inválido ou expirado. Solicite um novo link de recuperação.');

            return;
        }

        $this->reset('password', 'password_confirmation');
        $this->completed = true;
    }

    public function render(): View
    {
        return view('livewire.reset-password')
            ->layout('components.layouts.guest', ['title' => 'Redefinir senha']);
    }
}
