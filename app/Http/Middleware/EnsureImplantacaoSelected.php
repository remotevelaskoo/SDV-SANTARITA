<?php

namespace App\Http\Middleware;

use App\Models\Scopes\ImplantacaoScope;
use App\Models\UsuarioImplantacao;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garante que uma requisição autenticada tenha uma implantação ativa na
 * sessão antes de qualquer consulta implantação-escopada acontecer
 * (ADR-002 §10: "sessão deverá registrar implantação ativa").
 *
 * Silencioso quando não há ambiguidade: usuário com 0 ou 1 implantação
 * ativa passa direto (0 preserva o fallback já existente em
 * ImplantacaoContext; 1 é auto-selecionada). Só usuário com 2+ vê a tela
 * de seleção.
 */
class EnsureImplantacaoSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check() || $request->routeIs(['implantacao.selecionar', 'logout']) || $this->isSelectionUpdate($request)) {
            return $next($request);
        }

        // UsuarioImplantacao usa BelongsToImplantacao (ImplantacaoScope), que
        // chama ImplantacaoContext::current() — aqui é exatamente o processo
        // que resolve esse contexto, então a consulta precisa ignorar o
        // escopo (mesma exceção documentada em ImplantacaoScope).
        $ativas = UsuarioImplantacao::withoutGlobalScope(ImplantacaoScope::class)
            ->where('user_id', Auth::id())
            ->where('status', 'ativa')
            ->whereHas('implantacao', fn ($query) => $query->where('status', 'ativa'))
            ->get();

        if ($ativas->count() === 1) {
            session(['implantacao_atual_id' => $ativas->first()->implantacao_id]);

            return $next($request);
        }

        if ($ativas->count() > 1) {
            $selecionada = session('implantacao_atual_id');
            $valida = $selecionada && $ativas->contains('implantacao_id', $selecionada);

            if (! $valida) {
                return redirect()->route('implantacao.selecionar');
            }
        }

        return $next($request);
    }

    /**
     * A escolha da implantação é uma ação Livewire (POST no endpoint de
     * atualização do Livewire), que também passa por este middleware. Sem esta
     * exceção, quem tem 2+ implantações seria devolvido à própria tela de
     * seleção e nunca conseguiria escolher. Só passa a requisição que contém
     * exclusivamente o componente de seleção; o Livewire ainda valida a
     * assinatura de cada snapshot antes de executar a ação.
     */
    private function isSelectionUpdate(Request $request): bool
    {
        if (! app('livewire')->isLivewireRequest()) {
            return false;
        }

        $components = $request->input('components');

        if (! is_array($components) || $components === []) {
            return false;
        }

        foreach ($components as $component) {
            $snapshot = json_decode((string) data_get($component, 'snapshot'), true);

            if (data_get($snapshot, 'memo.name') !== 'implantacao-selection') {
                return false;
            }
        }

        return true;
    }
}
