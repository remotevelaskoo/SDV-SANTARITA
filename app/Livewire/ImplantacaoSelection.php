<?php

namespace App\Livewire;

use App\Models\Scopes\ImplantacaoScope;
use App\Models\UsuarioImplantacao;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ImplantacaoSelection extends Component
{
    public ?string $erro = null;

    /** @return list<array{id: string, nome: string, organizacao: string}> */
    public function implantacoes(): array
    {
        return UsuarioImplantacao::withoutGlobalScope(ImplantacaoScope::class)
            ->where('user_id', Auth::id())
            ->where('status', 'ativa')
            ->whereHas('implantacao', fn ($query) => $query->where('status', 'ativa'))
            ->with('implantacao.organizacao')
            ->get()
            ->map(fn (UsuarioImplantacao $vinculo) => [
                'id' => (string) $vinculo->implantacao->id,
                'nome' => $vinculo->implantacao->nome,
                'organizacao' => $vinculo->implantacao->organizacao->nome,
            ])
            ->values()
            ->all();
    }

    public function selecionar(string $implantacaoId): mixed
    {
        // Não confia no id recebido do clique — revalida a associação do
        // próprio usuário antes de gravar na sessão (ADR-002 §11.3:
        // "implantacao_id enviado em formulário não concede acesso").
        $valida = UsuarioImplantacao::withoutGlobalScope(ImplantacaoScope::class)
            ->where('user_id', Auth::id())
            ->where('implantacao_id', $implantacaoId)
            ->where('status', 'ativa')
            ->exists();

        if (! $valida) {
            $this->erro = 'Selecione uma das implantações listadas.';

            return null;
        }

        session(['implantacao_atual_id' => $implantacaoId]);

        // Sem navigate: true — a troca de implantação muda o layout inteiro
        // (menu, permissões efetivas), então um recarregamento completo é
        // mais seguro aqui do que uma transição SPA: evita qualquer risco
        // de a navegação para o dashboard correr antes do commit da sessão
        // desta requisição.
        return $this->redirectRoute('dashboard');
    }

    public function render(): View
    {
        return view('livewire.implantacao-selection', [
            'implantacoes' => $this->implantacoes(),
        ])->layout('components.layouts.guest', [
            'title' => 'Selecionar implantação',
        ]);
    }
}
