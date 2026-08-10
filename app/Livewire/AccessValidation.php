<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class AccessValidation extends Component
{
    private const DEMO_EXISTING_DOCUMENT = '11111111111';

    public string $contribution = 'yes';

    public string $paymentMethod = 'dinheiro';

    public string $notes = '';

    public string $denialReason = 'sem_autorizacao';

    public string $denialDetails = '';

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    public ?string $protocol = null;

    public bool $quickRegistrationOpen = false;

    public bool $quickPersonRegistered = false;

    public bool $quickDuplicateFound = false;

    public string $quickName = '';

    public string $quickDocument = '';

    public string $quickPhone = '';

    public string $quickAccessType = 'visitante';

    public string $quickResponsible = '';

    public string $quickPropertyCode = '';

    public string $quickNotes = '';

    /** @var array{name: string, initials: string, document: string, type: string, property: string, responsible: string, status: string, validity: string} */
    public array $currentPerson = [
        'name' => 'Marcos Vinicius da Silva',
        'initials' => 'MV',
        'document' => '***.654.321-**',
        'type' => 'Morador',
        'property' => 'Bloco A · Apto 102',
        'responsible' => 'Próprio morador',
        'status' => 'Cadastro ativo',
        'validity' => 'Acesso permanente',
    ];

    public function openQuickRegistration(): void
    {
        $this->quickRegistrationOpen = true;
        $this->quickDuplicateFound = false;
        $this->feedback = null;
        $this->protocol = null;
        $this->resetErrorBag();
    }

    public function cancelQuickRegistration(): void
    {
        $this->quickRegistrationOpen = false;
        $this->quickDuplicateFound = false;
        $this->resetQuickRegistrationFields();
        $this->resetErrorBag();
    }

    public function checkQuickDocument(): void
    {
        $this->quickDuplicateFound = $this->normalizeDocument($this->quickDocument) === self::DEMO_EXISTING_DOCUMENT;

        if ($this->quickDuplicateFound) {
            $this->addError('quickDocument', 'Esta pessoa já possui cadastro. Localize o registro existente para evitar duplicidade.');
        } else {
            $this->resetErrorBag('quickDocument');
        }
    }

    public function saveQuickRegistration(): void
    {
        $this->checkQuickDocument();

        if ($this->quickDuplicateFound) {
            return;
        }

        $this->validate([
            'quickName' => ['required', 'string', 'min:3', 'max:120'],
            'quickDocument' => ['required', 'string', 'min:11', 'max:20'],
            'quickPhone' => ['required', 'string', 'min:8', 'max:20'],
            'quickAccessType' => ['required', Rule::in(['visitante', 'prestador', 'morador', 'outro'])],
            'quickResponsible' => ['required_if:quickAccessType,visitante,prestador', 'nullable', 'string', 'max:120'],
            'quickPropertyCode' => ['required_if:quickAccessType,visitante,morador', 'nullable', 'string', 'max:40'],
            'quickNotes' => ['nullable', 'string', 'max:200'],
        ], [
            'required' => 'Preencha este campo para continuar.',
            'required_if' => 'Preencha este campo para o tipo de acesso selecionado.',
            'min' => 'Informe um valor válido.',
        ]);

        $this->currentPerson = [
            'name' => $this->quickName,
            'initials' => $this->initialsFromName($this->quickName),
            'document' => $this->maskDocument($this->quickDocument),
            'type' => match ($this->quickAccessType) {
                'visitante' => 'Visitante',
                'prestador' => 'Prestador',
                'morador' => 'Morador',
                default => 'Outro acesso',
            },
            'property' => $this->quickPropertyCode !== '' ? strtoupper($this->quickPropertyCode) : 'Não vinculado',
            'responsible' => $this->quickResponsible !== '' ? $this->quickResponsible : 'A confirmar',
            'status' => 'Cadastro mínimo',
            'validity' => 'Aguardando análise',
        ];
        $this->quickPersonRegistered = true;
        $this->quickRegistrationOpen = false;
        $this->feedback = [
            'variant' => 'warning',
            'title' => 'Cadastro rápido anexado ao atendimento',
            'message' => 'Os dados mínimos foram preservados. O cadastro ainda não autoriza a entrada e deve ser complementado depois.',
        ];
    }

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

        if ($this->quickPersonRegistered) {
            $this->feedback = [
                'variant' => 'warning',
                'title' => 'Liberação não realizada',
                'message' => 'O cadastro rápido não possui autorização válida. Salve o atendimento ou confirme a autorização por outro fluxo.',
            ];
            $this->protocol = 'SRA-20260810-004186';

            return;
        }

        $this->feedback = [
            'variant' => 'success',
            'title' => 'Validação demonstrativa concluída',
            'message' => 'O registro foi simulado. Nenhum portão ou equipamento real foi acionado.',
        ];
        $this->protocol = 'SRA-20260810-004185';
    }

    public function startNewValidation(): void
    {
        $this->reset([
            'notes', 'denialDetails', 'feedback', 'protocol', 'quickRegistrationOpen', 'quickPersonRegistered',
            'quickDuplicateFound', 'quickName', 'quickDocument', 'quickPhone', 'quickAccessType',
            'quickResponsible', 'quickPropertyCode', 'quickNotes', 'currentPerson',
        ]);
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

    private function resetQuickRegistrationFields(): void
    {
        $this->quickName = '';
        $this->quickDocument = '';
        $this->quickPhone = '';
        $this->quickAccessType = 'visitante';
        $this->quickResponsible = '';
        $this->quickPropertyCode = '';
        $this->quickNotes = '';
    }

    private function normalizeDocument(string $document): string
    {
        return preg_replace('/\D/', '', $document) ?? '';
    }

    private function maskDocument(string $document): string
    {
        $digits = $this->normalizeDocument($document);

        return strlen($digits) > 11 ? '**.***.'.substr($digits, -6, 3).'/****-**' : '***.***.'.substr($digits, -5, 3).'-**';
    }

    private function initialsFromName(string $name): string
    {
        return collect(explode(' ', trim($name)))
            ->filter(fn (string $part): bool => ! in_array(mb_strtolower($part), ['da', 'de', 'do', 'das', 'dos'], true))
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->take(2)
            ->join('');
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
