<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PreRegistrationQueue extends Component
{
    public string $statusFilter = 'aguardando';

    public string $search = '';

    public string $rejectionReason = 'documento_incompleto';

    public string $correctionItems = 'documento';

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    /** @var list<array{id: int, name: string, initials: string, type: string, document: string, submittedAt: string, vehicle: string, protocol: string, status: string, destination: string, responsible: string, period: string, alert: string|null}> */
    public array $records = [
        ['id' => 1, 'name' => 'Camila Andrade', 'initials' => 'CA', 'type' => 'Visitante', 'document' => '***.***.331-**', 'submittedAt' => '09/08/2026 às 15:57', 'vehicle' => 'ABC1D23', 'protocol' => 'PRE-SRA-X7K9M2', 'status' => 'aguardando', 'destination' => 'Bloco B · Apto 304', 'responsible' => 'Mariana Souza', 'period' => '10/08/2026 · 18:00 às 22:00', 'alert' => 'Aguardando há mais de 24 horas'],
        ['id' => 2, 'name' => 'Paulo Henrique Lima', 'initials' => 'PH', 'type' => 'Prestador', 'document' => '***.***.760-**', 'submittedAt' => '10/08/2026 às 08:42', 'vehicle' => 'DEF4G56', 'protocol' => 'PRE-SRA-M4N8Q1', 'status' => 'aguardando', 'destination' => 'Área comum · Manutenção', 'responsible' => 'Síndica Ana Ferreira', 'period' => '11/08/2026 · 08:00 às 17:00', 'alert' => null],
        ['id' => 3, 'name' => 'Renata Alves', 'initials' => 'RA', 'type' => 'Turista', 'document' => '***.***.218-**', 'submittedAt' => '10/08/2026 às 10:15', 'vehicle' => 'Sem veículo', 'protocol' => 'PRE-SRA-C2P5T8', 'status' => 'correcao', 'destination' => 'Praia do Santa Rita', 'responsible' => 'Não exige responsável de imóvel', 'period' => '12/08 a 16/08/2026', 'alert' => 'Selfie precisa ser reenviada'],
        ['id' => 4, 'name' => 'Felipe Martins', 'initials' => 'FM', 'type' => 'Visitante', 'document' => '***.***.004-**', 'submittedAt' => '09/08/2026 às 19:21', 'vehicle' => 'GHI7J89', 'protocol' => 'PRE-SRA-R6V3B7', 'status' => 'aprovado', 'destination' => 'Bloco A · Apto 112', 'responsible' => 'Eduardo Nogueira', 'period' => '10/08/2026 · 14:00 às 20:00', 'alert' => null],
        ['id' => 5, 'name' => 'Sérgio Luz', 'initials' => 'SL', 'type' => 'Prestador', 'document' => '***.***.447-**', 'submittedAt' => '08/08/2026 às 16:30', 'vehicle' => 'Sem veículo', 'protocol' => 'PRE-SRA-H9D2K4', 'status' => 'rejeitado', 'destination' => 'Bloco B · Apto 706', 'responsible' => 'Luciana Ferraz', 'period' => '09/08/2026 · 09:00 às 12:00', 'alert' => null],
    ];

    public function setStatusFilter(string $status): void
    {
        if (in_array($status, ['aguardando', 'aprovado', 'rejeitado', 'todos'], true)) {
            $this->statusFilter = $status;
        }
    }

    public function approve(int $id): void
    {
        $this->updateStatus($id, 'aprovado');
        $this->feedback = [
            'variant' => 'success',
            'title' => 'Pré-cadastro aprovado',
            'message' => 'A entrada ainda será validada pela portaria conforme as regras vigentes.',
        ];
    }

    public function reject(int $id): void
    {
        $this->validate([
            'rejectionReason' => ['required', Rule::in(['documento_incompleto', 'dados_divergentes', 'periodo_invalido', 'solicitacao_nao_confirmada'])],
        ]);

        $this->updateStatus($id, 'rejeitado');
        $this->feedback = [
            'variant' => 'danger',
            'title' => 'Pré-cadastro rejeitado',
            'message' => 'A decisão foi registrada. A observação interna não será enviada ao solicitante.',
        ];
    }

    public function requestCorrection(int $id): void
    {
        $this->validate([
            'correctionItems' => ['required', Rule::in(['dados_pessoais', 'documento', 'selfie', 'veiculo'])],
        ]);

        $this->updateStatus($id, 'correcao');
        $this->feedback = [
            'variant' => 'warning',
            'title' => 'Correção solicitada',
            'message' => 'O item selecionado foi enviado para correção, preservando a versão analisada.',
        ];
    }

    /** @return list<array{id: int, name: string, initials: string, type: string, document: string, submittedAt: string, vehicle: string, protocol: string, status: string, destination: string, responsible: string, period: string, alert: string|null}> */
    public function filteredRecords(): array
    {
        $search = mb_strtolower(trim($this->search));

        return array_values(array_filter($this->records, function (array $record) use ($search): bool {
            $matchesStatus = $this->statusFilter === 'todos' || $record['status'] === $this->statusFilter;
            $matchesSearch = $search === '' || str_contains(mb_strtolower(implode(' ', [$record['name'], $record['protocol'], $record['vehicle'], $record['destination']])), $search);

            return $matchesStatus && $matchesSearch;
        }));
    }

    private function updateStatus(int $id, string $status): void
    {
        foreach ($this->records as $index => $record) {
            if ($record['id'] === $id) {
                $this->records[$index]['status'] = $status;

                return;
            }
        }
    }

    public function render(): View
    {
        return view('livewire.pre-registration-queue', [
            'filteredRecords' => $this->filteredRecords(),
        ])->layout('components.layouts.app', [
            'title' => 'Pré-cadastros',
            'heading' => 'Pré-cadastros',
            'headingDescription' => 'Solicitações antecipadas aguardando análise da portaria',
        ]);
    }
}
