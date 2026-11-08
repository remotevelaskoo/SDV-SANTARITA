<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissoes): Response
    {
        $autorizado = collect($permissoes)->contains(
            fn (string $permissao) => $request->user()?->hasPermission($permissao)
        );

        if (! $autorizado) {
            // Requisição de ação do Livewire: recusa explícita, sem executar a
            // ação nem redirecionar uma chamada assíncrona.
            if ($request->hasHeader('X-Livewire')) {
                abort(403, 'Sem permissão para acessar esta área.');
            }

            return redirect()->route('home')->with('erro', 'Sem permissão para acessar esta área.');
        }

        return $next($request);
    }
}
