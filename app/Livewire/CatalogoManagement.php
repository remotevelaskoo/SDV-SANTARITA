<?php

namespace App\Livewire;

use App\Models\Catalogo;
use App\Models\CatalogoItem;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CatalogoManagement extends Component
{
    private const CATALOGO_CHAVE = 'motivos_negativa';

    public string $mode = 'list';

    public ?string $editingItemId = null;

    public string $codigo = '';

    public string $rotulo = '';

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    public function createItem(): void
    {
        $this->resetForm();
        $this->mode = 'form';
    }

    public function editItem(string $id): void
    {
        $item = CatalogoItem::query()->find($id);

        if (! $item) {
            return;
        }

        $this->editingItemId = $item->id;
        $this->codigo = $item->codigo;
        $this->rotulo = $item->rotulo;
        $this->feedback = null;
        $this->resetErrorBag();
        $this->mode = 'form';
    }

    public function backToList(): void
    {
        $this->mode = 'list';
        $this->editingItemId = null;
    }

    public function salvarItem(AuditService $audit): void
    {
        $catalogo = Catalogo::porChave(self::CATALOGO_CHAVE);

        if (! $catalogo) {
            return;
        }

        $this->validate([
            'codigo' => [
                'required', 'string', 'max:60', 'alpha_dash',
                Rule::unique('catalogo_itens', 'codigo')
                    ->where(fn ($query) => $query->where('catalogo_id', $catalogo->id))
                    ->ignore($this->editingItemId),
            ],
            'rotulo' => ['required', 'string', 'max:120'],
        ], [
            'required' => 'Preencha este campo.',
            'alpha_dash' => 'Use apenas letras, números, traço ou sublinhado.',
        ]);

        $item = $this->editingItemId ? CatalogoItem::query()->find($this->editingItemId) : null;
        $estavaEditando = $item !== null;

        if ($item) {
            $item->update(['rotulo' => $this->rotulo]);

            $audit->record(
                action: 'catalogo_item_alterado',
                module: 'catalogos',
                entityType: 'catalogo_itens',
                entityId: $item->id,
                changes: ['rotulo' => ['old' => $item->getOriginal('rotulo'), 'new' => $this->rotulo]],
            );
        } else {
            $item = CatalogoItem::query()->create([
                'catalogo_id' => $catalogo->id,
                'codigo' => $this->codigo,
                'rotulo' => $this->rotulo,
                'status' => 'ativo',
            ]);

            $audit->record(
                action: 'catalogo_item_criado',
                module: 'catalogos',
                entityType: 'catalogo_itens',
                entityId: $item->id,
            );
        }

        $this->mode = 'list';
        $this->editingItemId = null;
        $this->feedback = [
            'variant' => 'success',
            'title' => $estavaEditando ? 'Motivo atualizado' : 'Motivo criado',
            'message' => "\"{$item->rotulo}\" está disponível para uso.",
        ];
    }

    public function inativarItem(string $id, AuditService $audit): void
    {
        $item = CatalogoItem::query()->where('status', 'ativo')->find($id);

        if (! $item) {
            return;
        }

        $item->update(['status' => 'inativo']);

        $audit->record(
            action: 'catalogo_item_inativado',
            module: 'catalogos',
            entityType: 'catalogo_itens',
            entityId: $item->id,
        );

        $this->feedback = [
            'variant' => 'danger',
            'title' => 'Motivo inativado',
            'message' => "\"{$item->rotulo}\" não aparece mais para novos usos. Registros existentes continuam legíveis.",
        ];
    }

    public function reativarItem(string $id, AuditService $audit): void
    {
        $item = CatalogoItem::query()->where('status', 'inativo')->find($id);

        if (! $item) {
            return;
        }

        $item->update(['status' => 'ativo']);

        $audit->record(
            action: 'catalogo_item_reativado',
            module: 'catalogos',
            entityType: 'catalogo_itens',
            entityId: $item->id,
        );

        $this->feedback = [
            'variant' => 'success',
            'title' => 'Motivo reativado',
            'message' => "\"{$item->rotulo}\" voltou a ficar disponível.",
        ];
    }

    /** @return list<array<string, mixed>> */
    public function itensDoCatalogo(): array
    {
        $catalogo = Catalogo::porChave(self::CATALOGO_CHAVE);

        if (! $catalogo) {
            return [];
        }

        return CatalogoItem::query()
            ->where('catalogo_id', $catalogo->id)
            ->orderBy('ordem')
            ->orderBy('rotulo')
            ->get()
            ->map(fn (CatalogoItem $item) => [
                'id' => (string) $item->id,
                'codigo' => $item->codigo,
                'rotulo' => $item->rotulo,
                'status' => $item->status,
                'statusLabel' => $item->status === 'ativo' ? 'Ativo' : 'Inativo',
            ])
            ->values()
            ->all();
    }

    private function resetForm(): void
    {
        $this->editingItemId = null;
        $this->codigo = '';
        $this->rotulo = '';
        $this->feedback = null;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.catalogo-management', [
            'itensDoCatalogo' => $this->itensDoCatalogo(),
        ])->layout('components.layouts.app', [
            'title' => 'Motivos de negativa',
            'heading' => $this->mode === 'form' ? ($this->editingItemId ? 'Editar motivo' : 'Novo motivo') : 'Motivos de negativa',
            'headingDescription' => 'Opções usadas ao negar um acesso na Validação de entrada',
        ]);
    }
}
