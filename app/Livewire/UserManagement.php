<?php

namespace App\Livewire;

use App\Integracoes\Aplicacao\TurnosOperacionais;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\OperadorTurno;
use App\Models\Perfil;
use App\Models\User;
use App\Models\UsuarioImplantacao;
use App\Models\UsuarioPerfil;
use App\Services\AuditService;
use App\Services\Gestao\PainelGestaoNuvem;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Usuários do sistema (PO 15/11/2026):
 * - o administrador cria o colaborador com senha inicial, trocada no 1º acesso;
 * - marca quem entra no painel de gestão na nuvem e define a senha dele;
 * - pode redefinir a senha e derrubar (kickar) um colaborador: as sessões caem
 *   na hora e, se ele estiver num posto, o posto é liberado e o caixa vai para
 *   conferência (mesma regra da liberação forçada de posto).
 */
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

    public string $senhaInicial = '';

    public string $senhaInicial_confirmation = '';

    public bool $acessoPainel = false;

    public string $senhaPainel = '';

    public string $senhaPainel_confirmation = '';

    public string $novaSenha = '';

    public string $novaSenha_confirmation = '';

    public string $novoPerfilId = '';

    public string $derrubarMotivo = '';

    public string $blockReason = '';

    public string $inactivateReason = '';

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    /** @var array<int|string, int>|null */
    private ?array $onlineCache = null;

    /** @var array<int|string, string>|null */
    private ?array $postosCache = null;

    public function setStatusFilter(string $status): void
    {
        if (in_array($status, ['todos', 'online', 'pendente', 'ativo', 'bloqueado', 'inativo'], true)) {
            $this->statusFilter = $status;
        }
    }

    public function openUser(string $id): void
    {
        if (User::query()->whereKey($id)->whereHas('implantacoes', fn ($q) => $q->where('status', 'ativa'))->exists()) {
            $this->resetForm();
            $this->selectedUserId = $id;
            $this->mode = 'detail';
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

    public function saveUser(AuditService $audit, PainelGestaoNuvem $painel): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:160'],
            'username' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9._-]+$/'],
            'email' => ['required', 'email', 'max:190'],
            'perfilId' => ['required', 'string'],
            'senhaInicial' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
            'senhaPainel' => $this->acessoPainel ? ['nullable', 'string', 'min:10', 'max:72', 'confirmed'] : ['nullable'],
        ], [
            'required' => 'Preencha este campo.',
            'username.regex' => 'Use só letras minúsculas, números, ponto, hífen ou sublinhado. Ex.: joao.silva',
            'senhaInicial.min' => 'A senha inicial precisa ter pelo menos 8 caracteres.',
            'senhaInicial.confirmed' => 'A repetição não confere com a senha inicial.',
            'senhaPainel.min' => 'A senha do painel remoto precisa ter pelo menos 10 caracteres.',
            'senhaPainel.confirmed' => 'A repetição não confere com a senha do painel.',
        ]);

        if (User::query()->where('username', $this->username)->orWhere('email', $this->email)->exists()) {
            $this->addError('username', 'Já existe um usuário com essa identificação ou e-mail.');

            return;
        }

        $perfil = Perfil::query()->find($this->perfilId);
        if (! $perfil) {
            $this->addError('perfilId', 'Selecione um perfil válido.');

            return;
        }

        $user = DB::transaction(function () use ($perfil): User {
            $user = User::query()->create([
                'name' => $this->name,
                'username' => $this->username,
                'email' => $this->email,
                'password' => $this->senhaInicial,
                'status' => 'ativo',
                'trocar_senha' => true,
                'acesso_painel_remoto' => $this->acessoPainel,
            ]);
            UsuarioImplantacao::query()->firstOrCreate(['user_id' => $user->id], ['status' => 'ativa']);
            UsuarioPerfil::query()->create(['user_id' => $user->id, 'perfil_id' => $perfil->id, 'started_at' => now()]);

            return $user;
        });

        $audit->record(action: 'criou_usuario', module: 'usuarios', entityType: 'users', entityId: $user->id, metadata: ['perfil' => $perfil->nome, 'painel_remoto' => $this->acessoPainel]);

        $mensagem = "{$user->name} já pode entrar com a identificação {$user->username} e a senha inicial. No primeiro acesso o sistema pede uma senha pessoal.";
        $variant = 'success';
        if ($this->acessoPainel && $this->senhaPainel !== '') {
            [$ok, $texto] = $this->enviarSenhaPainel($painel, $user, $this->senhaPainel);
            $mensagem .= ' '.$texto;
            $variant = $ok ? 'success' : 'warning';
        }

        $this->resetForm();
        $this->selectedUserId = (string) $user->id;
        $this->mode = 'detail';
        $this->feedback = ['variant' => $variant, 'title' => 'Usuário criado', 'message' => $mensagem];
    }

    public function changePerfil(AuditService $audit): void
    {
        $user = $this->findSelected();
        $perfil = Perfil::query()->where('status', 'ativo')->find($this->novoPerfilId);
        if (! $user || ! $perfil) {
            $this->addError('novoPerfilId', 'Selecione um perfil válido.');

            return;
        }
        if ($this->isSelf($user)) {
            $this->addError('novoPerfilId', 'Você não pode trocar o próprio perfil.');

            return;
        }
        if (! $perfil->permissoes()->where('chave', 'usuarios.administrar')->exists() && $this->wouldRemoveLastAdministrator($user)) {
            $this->addError('novoPerfilId', 'Este é o último administrador ativo. Dê a permissão a outro usuário antes.');

            return;
        }

        DB::transaction(function () use ($user, $perfil): void {
            UsuarioPerfil::query()->where('user_id', $user->id)->whereNull('ended_at')->get()
                ->each(fn (UsuarioPerfil $vinculo) => $vinculo->update(['ended_at' => now()->max($vinculo->started_at->copy()->addSecond())]));
            UsuarioPerfil::query()->create(['user_id' => $user->id, 'perfil_id' => $perfil->id, 'started_at' => now()->addSecond()]);
        });
        $audit->record(action: 'trocou_perfil_usuario', module: 'usuarios', entityType: 'users', entityId: $user->id, metadata: ['perfil' => $perfil->nome]);

        $this->novoPerfilId = '';
        $this->feedback = ['variant' => 'success', 'title' => 'Perfil alterado', 'message' => "{$user->name} agora tem o perfil {$perfil->nome}. Vale a partir da próxima tela que ele abrir."];
    }

    public function resetPassword(AuditService $audit): void
    {
        $user = $this->findSelected();
        if (! $user) {
            return;
        }
        $this->validate(['novaSenha' => ['required', 'string', 'min:8', 'max:72', 'confirmed']], [
            'novaSenha.required' => 'Informe a nova senha inicial.',
            'novaSenha.min' => 'A senha precisa ter pelo menos 8 caracteres.',
            'novaSenha.confirmed' => 'A repetição não confere.',
        ]);

        $user->forceFill(['password' => $this->novaSenha, 'trocar_senha' => true])->save();
        $this->revokeUserSessions($user, $audit, 'senha_redefinida');
        $audit->record(action: 'redefiniu_senha_usuario', module: 'usuarios', entityType: 'users', entityId: $user->id, classification: 'restrita');

        $this->reset('novaSenha', 'novaSenha_confirmation');
        $this->feedback = ['variant' => 'success', 'title' => 'Senha redefinida', 'message' => "{$user->name} entra com a nova senha e escolhe uma senha pessoal no primeiro acesso. As sessões abertas foram encerradas."];
    }

    public function togglePainel(AuditService $audit, PainelGestaoNuvem $painel): void
    {
        $user = $this->findSelected();
        if (! $user) {
            return;
        }
        $user->forceFill(['acesso_painel_remoto' => ! $user->acesso_painel_remoto])->save();
        $audit->record(action: $user->acesso_painel_remoto ? 'liberou_painel_remoto' : 'removeu_painel_remoto', module: 'usuarios', entityType: 'users', entityId: $user->id);

        try {
            $painel->sincronizarGestores();
            $nuvem = '';
        } catch (\Throwable $e) {
            report($e);
            $nuvem = ' A nuvem não respondeu agora; a mudança vale no próximo envio automático (até 5 minutos).';
        }

        $this->feedback = $user->acesso_painel_remoto
            ? ['variant' => 'success', 'title' => 'Painel remoto liberado', 'message' => 'Defina abaixo a senha do painel para '.$user->name.'.'.$nuvem]
            : ['variant' => 'warning', 'title' => 'Painel remoto removido', 'message' => $user->name.' não entra mais no painel de gestão.'.$nuvem];
    }

    public function setPainelPassword(PainelGestaoNuvem $painel, AuditService $audit): void
    {
        $user = $this->findSelected();
        if (! $user || ! $user->acesso_painel_remoto) {
            return;
        }
        $this->validate(['senhaPainel' => ['required', 'string', 'min:10', 'max:72', 'confirmed']], [
            'senhaPainel.required' => 'Informe a senha do painel remoto.',
            'senhaPainel.min' => 'A senha do painel remoto precisa ter pelo menos 10 caracteres.',
            'senhaPainel.confirmed' => 'A repetição não confere.',
        ]);

        [$ok, $texto] = $this->enviarSenhaPainel($painel, $user, $this->senhaPainel);
        if ($ok) {
            $audit->record(action: 'definiu_senha_painel_remoto', module: 'usuarios', entityType: 'users', entityId: $user->id, classification: 'restrita');
        }
        $this->reset('senhaPainel', 'senhaPainel_confirmation');
        $this->feedback = ['variant' => $ok ? 'success' : 'danger', 'title' => $ok ? 'Senha do painel definida' : 'Senha do painel não definida', 'message' => $texto];
    }

    /** Kick: derruba as sessões e, se ele estiver num posto, libera o posto e manda o caixa para conferência. */
    public function kickUser(TurnosOperacionais $turnos, AuditService $audit): void
    {
        $user = $this->findSelected();
        if (! $user) {
            return;
        }
        $this->validate(['derrubarMotivo' => ['required', 'string', 'min:5', 'max:200']], [
            'derrubarMotivo.required' => 'Informe o motivo.',
            'derrubarMotivo.min' => 'Descreva o motivo com pelo menos 5 caracteres.',
        ]);
        if ($this->isSelf($user)) {
            $this->addError('derrubarMotivo', 'Você não pode derrubar a própria sessão por aqui. Use Sair.');

            return;
        }

        $turno = OperadorTurno::query()->where('user_id', $user->id)->whereNull('encerrado_em')->with('posto')->first();
        if ($turno?->posto) {
            try {
                $turnos->liberar($turno->posto, Auth::user(), $this->derrubarMotivo);
            } catch (RegraIntegracaoViolada $e) {
                $this->addError('derrubarMotivo', $e->getMessage());

                return;
            }
        }
        $this->revokeUserSessions($user, $audit, 'usuario_derrubado');
        $audit->record(action: 'derrubou_usuario', module: 'usuarios', entityType: 'users', entityId: $user->id, reasonCode: 'usuario_derrubado', metadata: ['motivo' => $this->derrubarMotivo, 'posto' => $turno?->posto?->nome]);

        $this->derrubarMotivo = '';
        $this->feedback = [
            'variant' => 'warning',
            'title' => 'Colaborador derrubado',
            'message' => $turno?->posto
                ? "{$user->name} saiu do sistema na hora. O posto {$turno->posto->nome} ficou livre e o caixa, se estava aberto, foi para conferência."
                : "{$user->name} saiu do sistema na hora. Ele pode entrar de novo com a própria senha.",
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

        // User é global (identidade compartilhada entre implantações, ADR-002
        // §10) — sem este filtro, um administrador veria usuários de toda
        // implantação do sistema, não só a atual.
        $query = User::query()
            ->whereHas('implantacoes', fn ($q) => $q->where('status', 'ativa'))
            ->with(['usuarioPerfis' => fn ($q) => $q->whereNull('ended_at')->with('perfil')]);

        if ($this->statusFilter === 'online') {
            $query->whereIn('id', array_keys($this->online()));
        } elseif ($this->statusFilter !== 'todos') {
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

    /** @return array{online: int, ativo: int, painel: int, bloqueado: int, inativo: int} */
    public function userCounts(): array
    {
        $base = fn () => User::query()->whereHas('implantacoes', fn ($q) => $q->where('status', 'ativa'));

        return [
            'online' => $base()->whereIn('id', array_keys($this->online()))->count(),
            'ativo' => $base()->where('status', 'ativo')->count(),
            'painel' => $base()->where('status', 'ativo')->where('acesso_painel_remoto', true)->count(),
            'bloqueado' => $base()->where('status', 'bloqueado')->count(),
            'inativo' => $base()->where('status', 'inativo')->count(),
        ];
    }

    /** Usuários com sessão usada nos últimos 15 minutos. @return array<int|string, int> id => último uso (timestamp) */
    private function online(): array
    {
        return $this->onlineCache ??= DB::table('sessions')->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes(15)->getTimestamp())
            ->get(['user_id', 'last_activity'])
            ->groupBy('user_id')
            ->map(fn ($sessoes) => (int) $sessoes->max('last_activity'))
            ->all();
    }

    /** Posto ocupado agora por cada operador. @return array<int|string, string> */
    private function postos(): array
    {
        return $this->postosCache ??= OperadorTurno::query()->whereNull('encerrado_em')->with('posto')->get()
            ->mapWithKeys(fn (OperadorTurno $turno) => [$turno->user_id => $turno->posto?->nome ?? 'Posto'])->all();
    }

    private function findSelected(): ?User
    {
        return $this->selectedUserId
            ? User::query()
                ->whereHas('implantacoes', fn ($q) => $q->where('status', 'ativa'))
                ->with(['usuarioPerfis' => fn ($q) => $q->whereNull('ended_at')->with('perfil')])
                ->find($this->selectedUserId)
            : null;
    }

    private function isSelf(User $user): bool
    {
        return (int) Auth::id() === (int) $user->id;
    }

    /** @return array{0: bool, 1: string} */
    private function enviarSenhaPainel(PainelGestaoNuvem $painel, User $user, string $senha): array
    {
        try {
            $painel->definirAcesso((string) $user->email, $senha);

            return [true, "Painel remoto pronto: {$user->name} entra com o e-mail {$user->email} e a senha do painel."];
        } catch (\Throwable $e) {
            report($e);

            return [false, 'A senha do painel remoto não foi gravada (a nuvem não respondeu ou o painel está desligado). Tente de novo no detalhe do usuário.'];
        }
    }

    private function revokeUserSessions(User $user, AuditService $audit, string $reasonCode): void
    {
        $deleted = DB::table('sessions')->where('user_id', $user->id)->delete();
        $this->onlineCache = null;

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
        $ultimoUso = $this->online()[$user->id] ?? null;

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
            'online' => $ultimoUso !== null,
            'ultimoUso' => $ultimoUso ? now()->setTimestamp((int) $ultimoUso)->format('H:i') : null,
            'posto' => $this->postos()[$user->id] ?? null,
            'painel' => (bool) $user->acesso_painel_remoto,
            'trocarSenha' => (bool) $user->trocar_senha,
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
        $this->reset('name', 'username', 'email', 'perfilId', 'senhaInicial', 'senhaInicial_confirmation', 'acessoPainel',
            'senhaPainel', 'senhaPainel_confirmation', 'novaSenha', 'novaSenha_confirmation', 'novoPerfilId', 'derrubarMotivo',
            'blockReason', 'inactivateReason', 'feedback');
        $this->resetErrorBag();
    }

    // Sem abort_unless(hasPermission(...)) aqui de propósito, diferente de
    // PerfilManagement/ConfiguracaoManagement/CatalogoManagement: exigir
    // usuarios.administrar para renderizar tornaria
    // wouldRemoveLastAdministrator() estruturalmente inalcançável — quem
    // chega ao componente já seria administrador, então bloquear "o último
    // admin" nunca dropa a zero (o próprio ator conta como outro admin
    // ativo). A rota já exige a permissão (routes/web.php); a correção real
    // desta fatia (openUser/findSelected sem escopo de implantação) não
    // depende disto.
    public function render(): View
    {
        $this->onlineCache = $this->postosCache = null;

        return view('livewire.user-management', [
            'filteredUsers' => $this->filteredUsers(),
            'selectedUser' => $this->selectedUser(),
            'userCounts' => $this->userCounts(),
            'perfis' => Perfil::query()->where('status', 'ativo')->orderBy('nome')->get(),
            'painelLigado' => (bool) config('pre_cadastro_nuvem.gestao_enabled'),
            'enderecoPainel' => rtrim((string) config('pre_cadastro_nuvem.url'), '/').'/gestao',
        ])->layout('components.layouts.app', [
            'title' => 'Usuários',
            'heading' => $this->mode === 'form' ? 'Novo usuário' : ($this->mode === 'detail' ? 'Detalhe do usuário' : 'Usuários'),
            'headingDescription' => 'Criação, perfis, painel remoto, senhas e sessões dos colaboradores',
        ]);
    }
}
