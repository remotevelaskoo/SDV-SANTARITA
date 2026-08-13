<?php

namespace App\Livewire;

use App\Models\Configuracao;
use App\Models\ImplantacaoConfiguracao;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ConfiguracaoManagement extends Component
{
    public string $mode = 'list';

    public ?string $editingChave = null;

    public string $valorInput = '';

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    public function editConfiguracao(string $chave): void
    {
        $configuracao = Configuracao::query()->where('chave', $chave)->first();

        if (! $configuracao) {
            return;
        }

        $this->editingChave = $chave;
        $this->valorInput = $configuracao->valorEfetivo() ?? '';
        $this->feedback = null;
        $this->resetErrorBag();
        $this->mode = 'form';
    }

    public function backToList(): void
    {
        $this->mode = 'list';
        $this->editingChave = null;
    }

    public function salvarConfiguracao(AuditService $audit): void
    {
        $configuracao = $this->findEditing();

        if (! $configuracao) {
            return;
        }

        $this->validate([
            'valorInput' => $configuracao->tipo === 'numero'
                ? ['nullable', 'numeric']
                : ['nullable', 'string', 'max:200'],
        ], [
            'numeric' => 'Informe um valor numérico válido.',
        ]);

        $valorAnterior = $configuracao->valorEfetivo();
        $novoValor = $this->valorInput === '' ? null : $this->valorInput;

        // Valor vazio se comporta como restaurar padrão — evita override
        // "customizado" que na prática mostra o mesmo valor do padrão.
        if ($novoValor === null) {
            ImplantacaoConfiguracao::query()->where('configuracao_id', $configuracao->id)->delete();
        } else {
            ImplantacaoConfiguracao::query()->updateOrCreate(
                ['configuracao_id' => $configuracao->id],
                ['valor' => $novoValor, 'updated_by' => Auth::id()],
            );
        }

        $audit->record(
            action: 'configuracao_alterada',
            module: 'configuracoes',
            entityType: 'configuracoes',
            entityId: $configuracao->id,
            changes: ['valor' => ['old' => $valorAnterior, 'new' => $novoValor ?? $configuracao->valor_padrao]],
        );

        $this->mode = 'list';
        $this->editingChave = null;
        $this->feedback = [
            'variant' => 'success',
            'title' => 'Configuração atualizada',
            'message' => "\"{$configuracao->rotulo}\" foi atualizada para esta implantação.",
        ];
    }

    public function restaurarPadrao(AuditService $audit): void
    {
        $configuracao = $this->findEditing();

        if (! $configuracao) {
            return;
        }

        $override = ImplantacaoConfiguracao::query()->where('configuracao_id', $configuracao->id)->first();

        if (! $override) {
            return;
        }

        $valorAnterior = $override->valor;
        $override->delete();

        $audit->record(
            action: 'configuracao_restaurada_padrao',
            module: 'configuracoes',
            entityType: 'configuracoes',
            entityId: $configuracao->id,
            changes: ['valor' => ['old' => $valorAnterior, 'new' => $configuracao->valor_padrao]],
        );

        $this->mode = 'list';
        $this->editingChave = null;
        $this->feedback = [
            'variant' => 'success',
            'title' => 'Padrão restaurado',
            'message' => "\"{$configuracao->rotulo}\" voltou a usar o valor padrão.",
        ];
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function configuracoesPorCategoria(): array
    {
        return Configuracao::query()
            ->orderBy('categoria')
            ->orderBy('rotulo')
            ->get()
            ->map(fn (Configuracao $configuracao) => $this->toArray($configuracao))
            ->groupBy('categoria')
            ->map(fn ($grupo) => $grupo->values()->all())
            ->all();
    }

    /** @return array<string, mixed>|null */
    public function editingConfiguracao(): ?array
    {
        $configuracao = $this->findEditing();

        return $configuracao ? $this->toArray($configuracao) : null;
    }

    private function findEditing(): ?Configuracao
    {
        return $this->editingChave
            ? Configuracao::query()->where('chave', $this->editingChave)->first()
            : null;
    }

    /** @return array<string, mixed> */
    private function toArray(Configuracao $configuracao): array
    {
        $override = ImplantacaoConfiguracao::query()->where('configuracao_id', $configuracao->id)->first();

        return [
            'chave' => $configuracao->chave,
            'categoria' => $configuracao->categoria,
            'tipo' => $configuracao->tipo,
            'rotulo' => $configuracao->rotulo,
            'descricao' => $configuracao->descricao,
            'valorPadrao' => $configuracao->valor_padrao,
            'valorAtual' => $override?->valor ?? $configuracao->valor_padrao,
            'isCustomizado' => $override !== null,
        ];
    }

    private function canManage(): bool
    {
        return Auth::user()?->hasPermission('configuracoes.gerenciar') ?? false;
    }

    public function render(): View
    {
        abort_unless($this->canManage(), 403);

        return view('livewire.configuracao-management', [
            'configuracoesPorCategoria' => $this->configuracoesPorCategoria(),
            'editingConfiguracao' => $this->editingConfiguracao(),
        ])->layout('components.layouts.app', [
            'title' => 'Configurações',
            'heading' => $this->mode === 'form' ? 'Editar configuração' : 'Configurações da implantação',
            'headingDescription' => 'Valores por implantação — configurações fora dessa lista ainda usam o padrão do sistema',
        ]);
    }
}
