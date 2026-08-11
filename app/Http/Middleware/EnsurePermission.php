<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permissao): Response
    {
        if (! $request->user()?->hasPermission($permissao)) {
            return redirect()->route('dashboard')->with('erro', 'Sem permissão para acessar esta área.');
        }

        return $next($request);
    }
}
