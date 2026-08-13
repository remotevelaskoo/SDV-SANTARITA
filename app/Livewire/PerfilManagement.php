<?php

namespace App\Livewire;

use App\Models\Perfil;
use App\Models\Permissao;
use App\Services\AuditService;
use App\Support\ImplantacaoContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PerfilManagement extends Component
{
    private const PERMISSOES_CRITICAS = ['usuarios.administrar', 'perfis.administrar'];

    public string $mode = 'list';

    public string $search = '';

    public string $statusFilter = 'todos';

    public ?string $selectedPerfilId = null;

    public ?string $editingPerfilId = null;

    public string $nome = '';

    /** @var list<string> */
    public array $permissaoIds = [];

    public string $inactivateReason = '';

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    public function setStatusFilter(string $status): void
    {
        if (in_array($status, ['todos', 'ativo', 'inativo'], true)) {
            $this->statusFilter = $status;
        }
    }

    public function openPerfil(string $id): void
    {
        if (Perfil::query()->whereKey($id)->exists()) {
            $this->selectedPerfilId = $id;
            $this->mode = 'detail';
            $this->inactivateReason = '';
            $this->feedback = null;
        }
    }

    public function backToList(): void
    {
        $this->mode = 'list';
        $this->selectedPerfilId = null;
    }

    public function createPerfil(): void
    {
        $this->resetForm();
        $this->mode = 'form';
    }

    public function editPerfil(string $id): void
    {
        $perfil = Perfil::query()->with('permissoes')->find($id);

        if (! $perfil) {
            return;
        }

        $this->editingPerfilId = $perfil->id;
        $this->nome = $perfil->nome;
        $this->permissaoIds = $perfil->permissoes->pluck('id')->all();
        $this->feedback = null;
        $this->resetErrorBag();
        $this->mode = 'form';
    }

    public function savePerfil(AuditService $audit): void
    {
        $this->validate([
            'nome' => [
                'required', 'string', 'max:120',
                Rule::unique('perfis', 'nome')
                    ->where(fn ($query) => $query->where('implantacao_id', ImplantacaoContext::current()->id))
                    ->ignore($this->editingPerfilId),
            ],
        ], ['required' => 'Preencha o nome do perfil.']);

        $perfil = $this->editingPerfilId ? Perfil::query()->find($this->editingPerfilId) : null;

        if ($this->editingPerfilId && ! $perfil) {
            return;
        }

        if ($perfil) {
            $bloqueio = $this->protegeUltimoConcedente($perfil, $this->permissaoIds);

            if ($bloqueio) {
                $this->addError('permissaoIds', $bloqueio);

                return;
            }
        }

        $nomeAnterior = $perfil?->nome;
        $permissoesAnteriores = $perfil ? $perfil->permissoes->pluck('chave')->sort()->values()->all() : [];

        if ($perfil) {
            $perfil->update(['nome' => $this->nome]);
        } else {
            $perfil = Perfil::query()->create(['nome' => $this->nome, 'status' => 'ativo']);
        }

        $perfil->permissoes()->sync($this->permissaoIds);

        $permissoesNovas = Permissao::query()->whereIn('id', $this->permissaoIds)->pluck('chave')->sort()->values()->all();

        $audit->record(
            action: $nomeAnterior === null ? 'perfil_criado' : 'perfil_alterado',
            module: 'usuarios',
            entityType: 'perfis',
            entityId: $perfil->id,
            changes: $nomeAnterior === null ? [] : [
                'nome' => ['old' => $nomeAnterior, 'new' => $this->nome],
                'permissoes' => ['old' => $permissoesAnteriores, 'new' => $permissoesNovas],
            ],
        );

        $this->selectedPerfilId = (string) $perfil->id;
        $this->editingPerfilId = null;
        $this->mode = 'detail';
        $this->feedback = [
            'variant' => 'success',
            'title' => $nomeAnterior === null ? 'Perfil criado' : 'Perfil atualizado',
            'message' => $nomeAnterior === null
                ? 'O novo perfil já está disponível para ser atribuído a usuários.'
                : 'Nome e permissões foram atualizados.',
        ];
    }

    public function inactivatePerfil(AuditService $audit): void
    {
        $perfil = $this->findSelected();

        if (! $perfil) {
            return;
        }

        $this->validate([
            'inactivateReason' => ['required', 'string', 'max:200'],
        ], ['required' => 'Informe o motivo da inativação.']);

        $bloqueio = $this->protegeUltimoConcedente($perfil, []);

        if ($bloqueio) {
            $this->addError('inactivateReason', $bloqueio);

            return;
        }

        $perfil->update(['status' => 'inativo']);

        $audit->record(
            action: 'perfil_inativado',
            module: 'usuarios',
            entityType: 'perfis',
            entityId: $perfil->id,
            justification: $this->inactivateReason,
        );

        $this->inactivateReason = '';
        $this->feedback = [
            'variant' => 'danger',
            'title' => 'Perfil inativado',
            'message' => 'O perfil não pode mais ser atribuído a novos usuários. O histórico de quem já teve esse perfil é preservado.',
        ];
    }

    public function reactivatePerfil(AuditService $audit): void
    {
        $perfil = $this->findSelected();

        if (! $perfil || $perfil->status !== 'inativo') {
            return;
        }

        $perfil->update(['status' => 'ativo']);

        $audit->record(
            action: 'perfil_reativado',
            module: 'usuarios',
            entityType: 'perfis',
            entityId: $perfil->id,
        );

        $this->feedback = [
            'variant' => 'success',
            'title' => 'Perfil reativado',
            'message' => 'O perfil pode voltar a ser atribuído a usuários.',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function filteredPerfis(): array
    {
        $search = mb_strtolower(trim($this->search));

        $query = Perfil::query()->with(['permissoes', 'usuarioPerfis' => fn ($q) => $q->whereNull('ended_at')->with('user')]);

        if ($this->statusFilter !== 'todos') {
            $query->where('status', $this->statusFilter);
        }

        $perfis = $query->orderBy('nome')->get();

        if ($search !== '') {
            $perfis = $perfis->filter(fn (Perfil $perfil) => str_contains(mb_strtolower($perfil->nome), $search));
        }

        return $perfis->map(fn (Perfil $perfil) => $this->toArray($perfil))->values()->all();
    }

    /** @return array<string, mixed>|null */
    public function selectedPerfil(): ?array
    {
        $perfil = $this->findSelected();

        return $perfil ? $this->toArray($perfil) : null;
    }

    /** @return array{ativo: int, inativo: int} */
    public function perfilCounts(): array
    {
        return [
            'ativo' => Perfil::query()->where('status', 'ativo')->count(),
            'inativo' => Perfil::query()->where('status', 'inativo')->count(),
        ];
    }

    /** @return array<string, Collection<int, Permissao>> */
    public function permissoesPorModulo(): array
    {
        return Permissao::query()->orderBy('modulo')->orderBy('descricao')->get()->groupBy('modulo')->all();
    }

    private function findSelected(): ?Perfil
    {
        return $this->selectedPerfilId
            ? Perfil::query()->with(['permissoes', 'usuarioPerfis' => fn ($q) => $q->whereNull('ended_at')->with('user')])->find($this->selectedPerfilId)
            : null;
    }

    /**
     * Bloqueia perder a última concessão ativa de uma permissão crítica: se
     * $permissoesFinais não incluir mais a chave crítica que $perfil concede
     * hoje, e nenhum outro perfil ativo com usuário ativo vigente a concede,
     * a ação é recusada (mesmo espírito de
     * UserManagement::wouldRemoveLastAdministrator()).
     *
     * @param  list<string>  $permissoesFinais  ids de Permissao que o perfil terá após a ação
     */
    private function protegeUltimoConcedente(Perfil $perfil, array $permissoesFinais): ?string
    {
        $chavesFinais = Permissao::query()->whereIn('id', $permissoesFinais)->pluck('chave')->all();

        foreach (self::PERMISSOES_CRITICAS as $chave) {
            $tinhaAntes = $perfil->concede($chave);
            $teraDepois = in_array($chave, $chavesFinais, true);

            if ($tinhaAntes && ! $teraDepois && $this->ehUnicoConcedenteAtivo($perfil, $chave)) {
                return "Este é o único perfil ativo que concede \"{$chave}\" a um usuário ativo. Conceda essa permissão a outro perfil antes de continuar.";
            }
        }

        return null;
    }

    private function ehUnicoConcedenteAtivo(Perfil $perfil, string $chave): bool
    {
        return ! Perfil::query()
            ->where('id', '!=', $perfil->id)
            ->where('status', 'ativo')
            ->whereHas('permissoes', fn ($q) => $q->where('chave', $chave))
            ->whereHas('usuarioPerfis', fn ($q) => $q->whereNull('ended_at')->whereHas('user', fn ($q) => $q->where('status', 'ativo')))
            ->exists();
    }

    /** @return array<string, mixed> */
    private function toArray(Perfil $perfil): array
    {
        $usuarios = $perfil->usuarioPerfis->map(fn ($vinculo) => $vinculo->user?->name)->filter()->values()->all();

        return [
            'id' => (string) $perfil->id,
            'nome' => $perfil->nome,
            'status' => $perfil->status,
            'statusLabel' => $perfil->status === 'ativo' ? 'Ativo' : 'Inativo',
            'permissoesCount' => $perfil->permissoes->count(),
            'usuariosCount' => count($usuarios),
            'usuarios' => $usuarios,
            'permissoesChaves' => $perfil->permissoes->pluck('chave')->sort()->values()->all(),
            'isCriticalAndLast' => collect(self::PERMISSOES_CRITICAS)->contains(
                fn (string $chave) => $perfil->concede($chave) && $this->ehUnicoConcedenteAtivo($perfil, $chave)
            ),
        ];
    }

    private function resetForm(): void
    {
        $this->editingPerfilId = null;
        $this->nome = '';
        $this->permissaoIds = [];
        $this->feedback = null;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.perfil-management', [
            'filteredPerfis' => $this->filteredPerfis(),
            'selectedPerfil' => $this->selectedPerfil(),
            'perfilCounts' => $this->perfilCounts(),
            'permissoesPorModulo' => $this->permissoesPorModulo(),
        ])->layout('components.layouts.app', [
            'title' => 'Perfis e permissões',
            'heading' => $this->mode === 'form' ? ($this->editingPerfilId ? 'Editar perfil' : 'Novo perfil') : ($this->mode === 'detail' ? 'Detalhe do perfil' : 'Perfis e permissões'),
            'headingDescription' => 'Catálogo de perfis e as permissões que cada um concede',
        ]);
    }
}
