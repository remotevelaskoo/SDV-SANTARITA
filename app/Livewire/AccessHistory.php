<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class AccessHistory extends Component
{
    public string $mode = 'list';

    public string $search = '';

    public string $typeFilter = 'todos';

    public string $resultFilter = 'todos';

    public string $pointFilter = 'todos';

    public ?int $selectedEntryId = null;

    /** @var list<array<string, mixed>> */
    public array $entries = [
        [
            'id' => 1, 'datetime' => '10/08/2026 16:04', 'name' => 'Luciana Ferraz', 'document' => '***.218.904-**', 'relation' => 'Prestador', 'property' => 'Bloco B — Apto 706', 'point' => 'Portão de Serviço', 'type' => 'entrada', 'result' => 'pendente', 'plate' => null, 'operator' => 'Tatiane Souza', 'reason' => null, 'notes' => 'Aguardando conferência do responsável pela empresa.', 'protocol' => 'SRA-20260810-004181',
        ],
        [
            'id' => 2, 'datetime' => '10/08/2026 15:58', 'name' => 'Eduardo Nogueira', 'document' => '***.760.115-**', 'relation' => 'Morador', 'property' => 'Bloco A — Apto 112', 'point' => 'Portaria Principal', 'type' => 'saida', 'result' => 'liberado', 'plate' => 'GFT4A09', 'operator' => 'Tatiane Souza', 'reason' => null, 'notes' => null, 'protocol' => 'SRA-20260810-004180',
        ],
        [
            'id' => 3, 'datetime' => '10/08/2026 15:41', 'name' => 'Bianca Moretti', 'document' => '***.615.338-**', 'relation' => 'Visitante', 'property' => 'Bloco A — Apto 208', 'point' => 'Portaria Principal', 'type' => 'entrada', 'result' => 'negado', 'plate' => null, 'operator' => 'Tatiane Souza', 'reason' => 'Responsável não localizado para confirmar a visita.', 'notes' => 'Visitante orientada a retornar após contato com o morador.', 'protocol' => 'SRA-20260810-004179',
        ],
        [
            'id' => 4, 'datetime' => '10/08/2026 15:33', 'name' => 'Sérgio Aparecido Luz', 'document' => '***.447.601-**', 'relation' => 'Prestador', 'property' => 'Bloco B — Apto 706', 'point' => 'Portão de Serviço', 'type' => 'entrada', 'result' => 'liberado', 'plate' => 'LMD7C44', 'operator' => 'Tatiane Souza', 'reason' => null, 'notes' => null, 'protocol' => 'SRA-20260810-004178',
        ],
        [
            'id' => 5, 'datetime' => '10/08/2026 12:20', 'name' => 'Camila Andrade', 'document' => '***.331.407-**', 'relation' => 'Visitante', 'property' => 'Bloco B — Apto 304', 'point' => 'Portaria Principal', 'type' => 'saida', 'result' => 'liberado', 'plate' => 'RQK8H21', 'operator' => 'Tatiane Souza', 'reason' => null, 'notes' => null, 'protocol' => 'SRA-20260810-004150',
        ],
        [
            'id' => 6, 'datetime' => '10/08/2026 09:47', 'name' => 'Camila Andrade', 'document' => '***.331.407-**', 'relation' => 'Visitante', 'property' => 'Bloco B — Apto 304', 'point' => 'Portaria Principal', 'type' => 'entrada', 'result' => 'liberado', 'plate' => 'RQK8H21', 'operator' => 'Tatiane Souza', 'reason' => null, 'notes' => 'Pré-cadastro aprovado antecipadamente.', 'protocol' => 'SRA-20260810-004112',
        ],
        [
            'id' => 7, 'datetime' => '09/08/2026 18:12', 'name' => 'Rafael Domingues', 'document' => '***.004.120-**', 'relation' => 'Proprietário', 'property' => 'Bloco C — Apto 501', 'point' => 'Portaria Principal', 'type' => 'entrada', 'result' => 'liberado', 'plate' => null, 'operator' => 'Marcos Almeida', 'reason' => null, 'notes' => null, 'protocol' => 'SRA-20260809-003988',
        ],
        [
            'id' => 8, 'datetime' => '09/08/2026 08:05', 'name' => 'Mariana Souza', 'document' => '***.331.407-**', 'relation' => 'Proprietária', 'property' => 'Bloco B — Apto 304', 'point' => 'Portão de Serviço', 'type' => 'entrada', 'result' => 'negado', 'plate' => null, 'operator' => 'Marcos Almeida', 'reason' => 'Ponto de acesso não autorizado para o tipo de vínculo.', 'notes' => null, 'protocol' => 'SRA-20260809-003921',
        ],
    ];

    public function setTypeFilter(string $type): void
    {
        if (in_array($type, ['todos', 'entrada', 'saida'], true)) {
            $this->typeFilter = $type;
        }
    }

    public function setResultFilter(string $result): void
    {
        if (in_array($result, ['todos', 'liberado', 'negado', 'pendente'], true)) {
            $this->resultFilter = $result;
        }
    }

    public function openEntry(int $id): void
    {
        if ($this->findEntry($id)) {
            $this->selectedEntryId = $id;
            $this->mode = 'detail';
        }
    }

    public function backToList(): void
    {
        $this->mode = 'list';
        $this->selectedEntryId = null;
    }

    /** @return list<array<string, mixed>> */
    public function filteredEntries(): array
    {
        $search = mb_strtolower(trim($this->search));

        return array_values(array_filter($this->entries, function (array $entry) use ($search): bool {
            $matchesType = $this->typeFilter === 'todos' || $entry['type'] === $this->typeFilter;
            $matchesResult = $this->resultFilter === 'todos' || $entry['result'] === $this->resultFilter;
            $matchesPoint = $this->pointFilter === 'todos' || $entry['point'] === $this->pointFilter;
            $matchesSearch = $search === '' || str_contains(mb_strtolower(implode(' ', [
                $entry['name'], $entry['document'], $entry['property'], $entry['plate'] ?? '', $entry['protocol'],
            ])), $search);

            return $matchesType && $matchesResult && $matchesPoint && $matchesSearch;
        }));
    }

    /** @return array<string, mixed>|null */
    public function selectedEntry(): ?array
    {
        return $this->selectedEntryId ? $this->findEntry($this->selectedEntryId) : null;
    }

    /** @return list<string> */
    public function points(): array
    {
        return collect($this->entries)->pluck('point')->unique()->sort()->values()->all();
    }

    /** @return array<string, mixed>|null */
    private function findEntry(int $id): ?array
    {
        foreach ($this->entries as $entry) {
            if ($entry['id'] === $id) {
                return $entry;
            }
        }

        return null;
    }

    public function render(): View
    {
        return view('livewire.access-history', [
            'filteredEntries' => $this->filteredEntries(),
            'selectedEntry' => $this->selectedEntry(),
            'points' => $this->points(),
        ])->layout('components.layouts.app', [
            'title' => 'Entradas e saídas',
            'heading' => $this->mode === 'detail' ? 'Detalhe do registro' : 'Entradas e saídas',
            'headingDescription' => 'Histórico de tentativas de acesso — registros não podem ser editados (RN-048)',
        ]);
    }
}
