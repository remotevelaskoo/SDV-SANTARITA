<?php

namespace App\Livewire;

use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Component;

/** Troca obrigatória da senha inicial no primeiro acesso (PO 15/11/2026). */
class TrocarSenha extends Component
{
    public string $password = '';

    public string $password_confirmation = '';

    public function salvar(AuditService $audit): void
    {
        $this->validate([
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], [
            'password.required' => 'Informe a nova senha.',
            'password.min' => 'A nova senha precisa ter pelo menos 8 caracteres.',
            'password.confirmed' => 'A confirmação não corresponde à nova senha.',
        ]);

        $usuario = Auth::user();
        if (Hash::check($this->password, (string) $usuario->password)) {
            $this->addError('password', 'Escolha uma senha diferente da senha inicial.');

            return;
        }

        $usuario->forceFill(['password' => $this->password, 'trocar_senha' => false])->save();
        $audit->record(action: 'trocou_senha_inicial', module: 'autenticacao', entityType: 'users', entityId: $usuario->id, classification: 'restrita');

        $this->redirectRoute('home', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.trocar-senha')->layout('components.layouts.guest', ['title' => 'Trocar senha']);
    }
}
