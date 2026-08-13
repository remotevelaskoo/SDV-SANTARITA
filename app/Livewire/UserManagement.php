<?php

namespace App\Livewire;

use App\Models\Perfil;
use App\Models\User;
use App\Models\UsuarioImplantacao;
use App\Models\UsuarioPerfil;
use App\Notifications\UserInvited;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Component;

class UserManagement extends Component
{
    public string $mode = 'list';

    public string $search = '';

    public string $statusFilter = 'todos';

    public ?string $selectedUserId = null;

    public string $name = '';

    public string $username = '';

    public string $email = '';

    public string $perfilId = '';

    public string $blockReason = '';

    public string $inactivateReason = '';

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    public function setStatusFilter(string $status): void
    {
        if (in_array($status, ['todos', 'pendente', 'ativo', 'bloqueado', 'inativo'], true)) {
            $this->statusFilter = $status;
        }
    }

    public function openUser(string $id): void
    {
        if (User::query()->whereKey($id)->exists()) {
            $this->selectedUserId = $id;
            $this->mode = 'detail';
            $this->blockReason = '';
            $this->inactivateReason = '';
            $this->feedback = null;
        }
    }

    public function backToList(): void
    {
        $this->mode = 'list';
        $this->selectedUserId = null;
    }

    public function createUser(): void
    {
        $this->resetForm();
        $this->mode = 'form';
    }

    public function saveUser(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:160'],
            'username' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:190'],
            'perfilId' => ['required', 'string'],
        ], [
            'required' => 'Preencha este campo para enviar o convite.',
        ]);

        $duplicate = User::query()
            ->where('username', $this->username)
            ->orWhere('email', $this->email)
            ->exists();

        if ($duplicate) {
            $this->addError('username', 'Já existe um usuário com essa identificação ou e-mail.');

            return;
        }

        $perfil = Perfil::query()->find($this->perfilId);

        if (! $perfil) {
            $this->addError('perfilId', 'Selecione um perfil válido.');

            return;
        }

        $user = User::query()->create([
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'password' => Hash::make(Str::random(40)),
            'status' => 'pendente',
            'invited_at' => now(),
        ]);

        UsuarioImplantacao::query()->firstOrCreate([
            'user_id' => $user->id,
        ], ['status' => 'ativa']);

        UsuarioPerfil::query()->create([
            'user_id' => $user->id,
            'perfil_id' => $perfil->id,
            'started_at' => now(),
        ]);

        $user->notify(new UserInvited(Password::createToken($user)));

        $this->selectedUserId = (string) $user->id;
        $this->mode = 'detail';
        $this->feedback = [
            'variant' => 'success',
            'title' => 'Convite enviado',
            'message' => "O convite foi enviado para {$user->email}. A conta fica pendente até a pessoa definir a própria senha.",
        ];
    }

    public function blockUser(AuditService $audit): void
    {
        $user = $this->findSelected();

        if (! $user) {
            return;
        }

        $this->validate([
            'blockReason' => ['required', 'string', 'max:200'],
        ], ['required' => 'Informe o motivo do bloqueio.']);

        if ($this->isSelf($user)) {
            $this->addError('blockReason', 'Você não pode bloquear a própria conta.');

            return;
        }

        if ($this->wouldRemoveLastAdministrator($user)) {
            $this->addError('blockReason', 'Este é o último administrador ativo. Atribua a permissão a outro usuário antes de bloquear.');

            return;
        }

        $user->update([
            'status' => 'bloqueado',
            'status_reason' => $this->blockReason,
            'status_changed_by' => Auth::id(),
            'status_changed_at' => now(),
        ]);

        $this->revokeUserSessions($user, $audit, 'usuario_bloqueado');

        $this->blockReason = '';
        $this->feedback = [
            'variant' => 'warning',
            'title' => 'Usuário bloqueado',
            'message' => 'O acesso foi impedido imediatamente. O bloqueio pode ser revertido a qualquer momento.',
        ];
    }

    public function unblockUser(): void
    {
        $user = $this->findSelected();

        if (! $user || $user->status !== 'bloqueado') {
            return;
        }

        $user->update([
            'status' => 'ativo',
            'status_reason' => null,
            'status_changed_by' => Auth::id(),
            'status_changed_at' => now(),
        ]);

        $this->feedback = [
            'variant' => 'success',
            'title' => 'Usuário desbloqueado',
            'message' => 'O acesso foi restabelecido.',
        ];
    }

    public function inactivateUser(AuditService $audit): void
    {
        $user = $this->findSelected();

        if (! $user) {
            return;
        }

        $this->validate([
            'inactivateReason' => ['required', 'string', 'max:200'],
        ], ['required' => 'Informe o motivo da inativação.']);

        if ($this->isSelf($user)) {
            $this->addError('inactivateReason', 'Você não pode inativar a própria conta.');

            return;
        }

        if ($this->wouldRemoveLastAdministrator($user)) {
            $this->addError('inactivateReason', 'Este é o último administrador ativo. Atribua a permissão a outro usuário antes de inativar.');

            return;
        }

        $user->update([
            'status' => 'inativo',
            'status_reason' => $this->inactivateReason,
            'status_changed_by' => Auth::id(),
            'status_changed_at' => now(),
        ]);

        UsuarioPerfil::query()
            ->where('user_id', $user->id)
            ->whereNull('ended_at')
            ->get()
            ->each(fn (UsuarioPerfil $vinculo) => $vinculo->update([
                // started_at tem precisão de segundo (dateTime); garante
                // ended_at estritamente posterior mesmo se ambos caírem
                // no mesmo segundo (ex.: perfil recém-atribuído).
                'ended_at' => now()->max($vinculo->started_at->copy()->addSecond()),
            ]));

        $this->revokeUserSessions($user, $audit, 'usuario_inativado');

        $this->inactivateReason = '';
        $this->feedback = [
            'variant' => 'danger',
            'title' => 'Usuário inativado',
            'message' => 'O acesso foi encerrado e os perfis vigentes foram desvinculados. O histórico permanece preservado.',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function filteredUsers(): array
    {
        $search = mb_strtolower(trim($this->search));

        $query = User::query()->with(['usuarioPerfis' => fn ($q) => $q->whereNull('ended_at')->with('perfil')]);

        if ($this->statusFilter !== 'todos') {
            $query->where('status', $this->statusFilter);
        }

        $users = $query->orderBy('name')->get();

        if ($search !== '') {
            $users = $users->filter(fn (User $user) => str_contains(
                mb_strtolower(implode(' ', array_filter([$user->name, $user->username, $user->email]))),
                $search
            ));
        }

        return $users->map(fn (User $user) => $this->toArray($user))->values()->all();
    }

    /** @return array<string, mixed>|null */
    public function selectedUser(): ?array
    {
        $user = $this->findSelected();

        return $user ? $this->toArray($user) : null;
    }

    /** @return array{pendente: int, ativo: int, bloqueado: int, inativo: int} */
    public function userCounts(): array
    {
        return [
            'pendente' => User::query()->where('status', 'pendente')->count(),
            'ativo' => User::query()->where('status', 'ativo')->count(),
            'bloqueado' => User::query()->where('status', 'bloqueado')->count(),
            'inativo' => User::query()->where('status', 'inativo')->count(),
        ];
    }

    private function findSelected(): ?User
    {
        return $this->selectedUserId
            ? User::query()->with(['usuarioPerfis' => fn ($q) => $q->whereNull('ended_at')->with('perfil')])->find($this->selectedUserId)
            : null;
    }

    private function isSelf(User $user): bool
    {
        return (int) Auth::id() === (int) $user->id;
    }

    private function revokeUserSessions(User $user, AuditService $audit, string $reasonCode): void
    {
        $deleted = DB::table('sessions')->where('user_id', $user->id)->delete();

        if ($deleted > 0) {
            $audit->record(
                action: 'revogou_sessoes_usuario',
                module: 'usuarios',
                entityType: 'users',
                entityId: $user->id,
                reasonCode: $reasonCode,
                classification: 'restrita',
                metadata: ['quantity' => $deleted],
            );
        }
    }

    private function wouldRemoveLastAdministrator(User $user): bool
    {
        if (! $user->hasPermission('usuarios.administrar')) {
            return false;
        }

        return ! UsuarioPerfil::query()
            ->whereNull('ended_at')
            ->where('user_id', '!=', $user->id)
            ->whereHas('user', fn ($query) => $query->where('status', 'ativo'))
            ->whereHas('perfil', fn ($query) => $query->whereHas('permissoes', fn ($query) => $query->where('chave', 'usuarios.administrar')))
            ->exists();
    }

    /** @return array<string, mixed> */
    private function toArray(User $user): array
    {
        $vinculo = $user->usuarioPerfis->first();

        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'username' => $user->username ?? '—',
            'email' => $user->email,
            'status' => $user->status,
            'statusLabel' => $this->statusLabel($user->status),
            'statusReason' => $user->status_reason,
            'perfilAtual' => $vinculo?->perfil?->nome ?? 'Sem perfil vigente',
            'invitedAt' => $user->invited_at?->format('d/m/Y H:i'),
            'isSelf' => $this->isSelf($user),
            'isLastAdmin' => $this->wouldRemoveLastAdministrator($user),
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pendente' => 'Convite pendente',
            'ativo' => 'Ativo',
            'bloqueado' => 'Bloqueado',
            'inativo' => 'Inativo',
            default => ucfirst($status),
        };
    }

    private function resetForm(): void
    {
        $this->name = '';
        $this->username = '';
        $this->email = '';
        $this->perfilId = '';
        $this->feedback = null;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.user-management', [
            'filteredUsers' => $this->filteredUsers(),
            'selectedUser' => $this->selectedUser(),
            'userCounts' => $this->userCounts(),
            'perfis' => Perfil::query()->where('status', 'ativo')->orderBy('nome')->get(),
        ])->layout('components.layouts.app', [
            'title' => 'Usuários',
            'heading' => $this->mode === 'form' ? 'Convidar usuário' : ($this->mode === 'detail' ? 'Detalhe do usuário' : 'Usuários'),
            'headingDescription' => 'Convites, bloqueio e inativação de contas operacionais',
        ]);
    }
}
