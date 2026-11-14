<?php

namespace App\Http\Middleware;

use App\Integracoes\Aplicacao\TurnosOperacionais;
use App\Models\OperadorTurno;
use App\Models\Scopes\ImplantacaoScope;
use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quando Gestor ou Administrador libera o posto de um operador (SDV-OPE-018
 * §14, "Posto preso"), a sessão desse operador cai: na próxima requisição
 * (navegação ou clique Livewire) ele é deslogado e volta para a tela de
 * entrada com o aviso do que aconteceu.
 *
 * A sessão guarda `autenticado_em` no login; só derruba se a liberação
 * aconteceu depois disso, então quem entra de novo continua normalmente.
 */
class EncerrarSessaoDePostoLiberado
{
    /** Cabeçalho que o app.js usa para mandar uma tela Livewire ao login. */
    public const CABECALHO = 'X-SDV-Sessao-Encerrada';

    public function __construct(private AuditService $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::user();
        if ($usuario === null || $request->routeIs('logout')) {
            return $next($request);
        }

        $autenticadoEm = $request->session()->get('autenticado_em');
        if ($autenticadoEm === null) {
            // Sessão anterior a esta regra: passa a contar a partir de agora.
            $request->session()->put('autenticado_em', now()->getTimestamp());

            return $next($request);
        }

        $turno = OperadorTurno::query()
            ->withoutGlobalScope(ImplantacaoScope::class)
            ->where('user_id', $usuario->id)
            ->where('motivo_encerramento', TurnosOperacionais::MOTIVO_LIBERADO)
            ->where('encerrado_em', '>=', Carbon::createFromTimestamp($autenticadoEm, config('app.timezone')))
            ->with(['posto' => fn ($query) => $query->withoutGlobalScope(ImplantacaoScope::class)])
            ->latest('encerrado_em')
            ->first();

        if ($turno === null) {
            return $next($request);
        }

        $this->audit->record(
            action: 'sessao_encerrada_por_liberacao_de_posto',
            module: 'autenticacao',
            entityType: 'sessao',
            entityId: hash('sha256', $request->session()->getId()),
            metadata: ['operador_turno_id' => $turno->id, 'posto_id' => $turno->posto_id],
            classification: 'restrita',
        );

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $posto = $turno->posto?->nome ?? 'seu posto';
        $request->session()->flash(
            'sessao_encerrada',
            "O posto {$posto} foi liberado por um gestor ou administrador às {$turno->encerrado_em->format('H:i')}. "
            .'Seu turno foi encerrado e o caixa, se estava aberto, foi para conferência. Entre de novo para assumir um posto.',
        );

        $destino = route('login');

        if ($request->hasHeader('X-Livewire')) {
            return response()->json(['redirect' => $destino], 401, [self::CABECALHO => $destino]);
        }

        return redirect()->to($destino);
    }
}
