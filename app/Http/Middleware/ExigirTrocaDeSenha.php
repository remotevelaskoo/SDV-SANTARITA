<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Colaborador criado (ou com senha redefinida) pelo administrador entra com a
 * senha inicial e precisa trocá-la antes de usar o sistema (PO 15/11/2026).
 */
class ExigirTrocaDeSenha
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::user()?->trocar_senha || $request->routeIs('senha.trocar', 'logout') || $request->routeIs('livewire.*')) {
            return $next($request);
        }

        return redirect()->route('senha.trocar');
    }
}
