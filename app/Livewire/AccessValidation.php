<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class AccessValidation extends Component
{
    public string $contribution = 'yes';

    public string $paymentMethod = 'dinheiro';

    public string $notes = '';

    public string $denialReason = 'sem_autorizacao';

    public string $denialDetails = '';

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    public ?string $protocol = null;

    public function deny(): void
    {
        $this->validate([
            'denialReason' => ['required', Rule::in(['sem_autorizacao', 'documento_invalido', 'vinculo_irregular', 'decisao_operador'])],
            'denialDetails' => ['nullable', 'string', 'max:200'],
        ]);

        $this->feedback = [
            'variant' => 'danger',
            'title' => 'Entrada negada e registrada',
            'message' => 'Nenhum comando de abertura foi enviado. Resultado demonstrativo.',
        ];
        $this->protocol = 'SRA-20260810-004183';
    }

    public function savePending(): void
    {
        $this->validateCommonFields();

        $this->feedback = [
            'variant' => 'warning',
            'title' => 'Atendimento salvo sem liberação',
            'message' => 'Os dados foram preservados para continuação posterior. Nenhum comando foi enviado.',
        ];
        $this->protocol = 'SRA-20260810-004184';
    }

    public function release(): void
    {
        $this->validateCommonFields();

        $this->feedback = [
            'variant' => 'success',
            'title' => 'Validação demonstrativa concluída',
            'message' => 'O registro foi simulado. Nenhum portão ou equipamento real foi acionado.',
        ];
        $this->protocol = 'SRA-20260810-004185';
    }

    public function startNewValidation(): void
    {
        $this->reset(['notes', 'denialDetails', 'feedback', 'protocol']);
        $this->contribution = 'yes';
        $this->paymentMethod = 'dinheiro';
        $this->denialReason = 'sem_autorizacao';
    }

    private function validateCommonFields(): void
    {
        $this->validate([
            'contribution' => ['required', Rule::in(['yes', 'no', 'exempt'])],
            'paymentMethod' => ['required_if:contribution,yes', Rule::in(['dinheiro', 'pix', 'cartao'])],
            'notes' => ['nullable', 'string', 'max:200'],
        ]);
    }

    public function render(): View
    {
        return view('livewire.access-validation')
            ->layout('components.layouts.app', [
                'title' => 'Validação de entrada',
                'heading' => 'Validação de entrada',
                'headingDescription' => 'Confira os dados e decida sobre este acesso demonstrativo',
            ]);
    }
}
