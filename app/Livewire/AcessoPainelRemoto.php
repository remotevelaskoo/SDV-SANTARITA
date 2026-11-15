<?php

namespace App\Livewire;

use App\Services\Gestao\PainelGestaoNuvem;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Cada gestor cria a própria senha do painel remoto pelo sistema (PO 15/11/2026).
 * A digitação escondida no terminal do Windows estragava a senha; aqui ela vai
 * direto do navegador para a nuvem e não fica guardada no servidor local.
 */
class AcessoPainelRemoto extends Component
{
    public string $senhaAtual = '';

    public string $senha = '';

    public string $senha_confirmation = '';

    public ?string $mensagem = null;

    public ?string $erro = null;

    public function boot(): void
    {
        abort_unless(auth()->user()?->hasPermission(PainelGestaoNuvem::PERMISSAO), 403);
    }

    public function salvar(PainelGestaoNuvem $painel): void
    {
        $this->mensagem = $this->erro = null;
        $this->validate([
            'senhaAtual' => ['required', 'string'],
            'senha' => ['required', 'string', 'min:10', 'max:72', 'confirmed'],
        ], [
            'senhaAtual.required' => 'Informe a sua senha do SDV para confirmar.',
            'senha.required' => 'Escolha a senha do painel remoto.',
            'senha.min' => 'A senha do painel remoto precisa ter pelo menos 10 caracteres.',
            'senha.max' => 'A senha do painel remoto pode ter no máximo 72 caracteres.',
            'senha.confirmed' => 'A repetição não confere com a senha.',
        ]);

        $usuario = auth()->user();
        $chave = 'acesso-painel-remoto:'.$usuario->getKey();
        if (RateLimiter::tooManyAttempts($chave, 5)) {
            $this->addError('senhaAtual', 'Muitas tentativas. Aguarde um minuto e tente de novo.');

            return;
        }
        if (! Hash::check($this->senhaAtual, (string) $usuario->password)) {
            RateLimiter::hit($chave, 60);
            $this->addError('senhaAtual', 'Senha do SDV incorreta.');

            return;
        }
        RateLimiter::clear($chave);

        try {
            $painel->definirAcesso((string) $usuario->email, $this->senha);
            $this->mensagem = 'Acesso remoto pronto. Entre no painel com o seu e-mail e a senha que acabou de criar.';
        } catch (\Throwable $e) {
            report($e);
            $this->erro = 'Não foi possível criar o acesso agora. Confira a internet do servidor e tente de novo.';
        } finally {
            $this->reset('senhaAtual', 'senha', 'senha_confirmation');
        }
    }

    public function render(): View
    {
        return view('livewire.acesso-painel-remoto', [
            'ativo' => (bool) config('pre_cadastro_nuvem.gestao_enabled'),
            'endereco' => rtrim((string) config('pre_cadastro_nuvem.url'), '/').'/gestao',
            'email' => auth()->user()?->email,
        ]);
    }
}
