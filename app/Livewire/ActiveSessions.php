<?php

namespace App\Livewire;

use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ActiveSessions extends Component
{
    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    public function revoke(string $sessionId, AuditService $audit): void
    {
        if ($sessionId === session()->getId()) {
            return;
        }

        $deleted = DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', Auth::id())
            ->delete();

        if ($deleted) {
            $audit->record(
                action: 'revogou_sessao',
                module: 'autenticacao',
                entityType: 'sessao',
                entityId: hash('sha256', $sessionId),
                classification: 'restrita',
            );
            $this->feedback = [
                'variant' => 'success',
                'title' => 'Sessão encerrada',
                'message' => 'O acesso naquele dispositivo foi revogado imediatamente.',
            ];
        }
    }

    public function revokeOthers(AuditService $audit): void
    {
        $deleted = DB::table('sessions')
            ->where('user_id', Auth::id())
            ->where('id', '!=', session()->getId())
            ->delete();

        if ($deleted > 0) {
            $audit->record(
                action: 'revogou_outras_sessoes',
                module: 'autenticacao',
                entityType: 'sessao',
                metadata: ['quantity' => $deleted],
                classification: 'restrita',
            );
        }

        $this->feedback = $deleted > 0
            ? ['variant' => 'success', 'title' => 'Sessões encerradas', 'message' => "Encerramos {$deleted} outra(s) sessão(ões). Este dispositivo continua conectado."]
            : ['variant' => 'info', 'title' => 'Nada a encerrar', 'message' => 'Não há outras sessões ativas além desta.'];
    }

    /** @return list<array{id: string, ipAddress: string, userAgent: string, lastActivity: string, isCurrent: bool}> */
    public function activeSessions(): array
    {
        $currentId = session()->getId();

        return DB::table('sessions')
            ->where('user_id', Auth::id())
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'ipAddress' => $row->ip_address ?? '—',
                'userAgent' => $row->user_agent ?? '—',
                'lastActivity' => Carbon::createFromTimestamp($row->last_activity)->diffForHumans(),
                'isCurrent' => $row->id === $currentId,
            ])
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('livewire.active-sessions', [
            'activeSessions' => $this->activeSessions(),
        ])->layout('components.layouts.app', [
            'title' => 'Sessões ativas',
            'heading' => 'Sessões ativas',
            'headingDescription' => 'Dispositivos conectados com o seu usuário',
        ]);
    }
}
