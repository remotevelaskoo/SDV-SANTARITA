<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CashRegister extends Component
{
    public string $status = 'aberto';

    public string $terminal = 'Caixa 01 — Guarita';

    public string $operator = 'Tatiane Souza';

    public string $openedAt = '13:00';

    public float $openingBalance = 200.00;

    // Formulário de abertura
    public string $openingBalanceInput = '200,00';

    /** Regra reutilizada pelos três campos de valor (aceita vírgula decimal, ex.: 25,00). */
    private const MONEY_REGEX = '/^\d+([.,]\d{1,2})?$/';

    // Formulário de movimentação manual
    public string $movementType = 'entrada';

    public string $movementAmount = '';

    public string $movementMethod = 'dinheiro';

    public string $movementDescription = '';

    // Formulário de fechamento
    public string $informedAmount = '';

    public string $closingNotes = '';

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    /** @var list<array<string, mixed>> */
    public array $movements = [
        ['id' => 1, 'time' => '13:05', 'type' => 'entrada', 'amount' => 15.00, 'method' => 'dinheiro', 'description' => 'Contribuição — visitante Camila Andrade', 'protocol' => 'SRA-20260810-004112', 'operator' => 'Tatiane Souza'],
        ['id' => 2, 'time' => '13:33', 'type' => 'entrada', 'amount' => 15.00, 'method' => 'pix', 'description' => 'Contribuição — visitante Camila Andrade', 'protocol' => 'SRA-20260810-004150', 'operator' => 'Tatiane Souza'],
        ['id' => 3, 'time' => '15:33', 'type' => 'entrada', 'amount' => 20.00, 'method' => 'dinheiro', 'description' => 'Contribuição — prestador Sérgio Aparecido Luz', 'protocol' => 'SRA-20260810-004178', 'operator' => 'Tatiane Souza'],
        ['id' => 4, 'time' => '15:41', 'type' => 'estorno', 'amount' => 15.00, 'method' => 'dinheiro', 'description' => 'Estorno — entrada negada (Bianca Moretti)', 'protocol' => 'SRA-20260810-004179', 'operator' => 'Tatiane Souza'],
    ];

    /** @var list<array<string, mixed>> */
    public array $closedSessions = [
        ['id' => 1, 'terminal' => 'Caixa 01 — Guarita', 'operator' => 'Marcos Almeida', 'period' => '09/08/2026 07:00 às 19:00', 'opening' => 150.00, 'expected' => 612.50, 'informed' => 612.50, 'difference' => 0.0, 'status' => 'conferido'],
        ['id' => 2, 'terminal' => 'Caixa 01 — Guarita', 'operator' => 'Tatiane Souza', 'period' => '08/08/2026 07:00 às 19:00', 'opening' => 150.00, 'expected' => 498.00, 'informed' => 490.00, 'difference' => -8.0, 'status' => 'diferenca'],
    ];

    public function openRegister(): void
    {
        $this->validate([
            'openingBalanceInput' => ['required', 'regex:'.self::MONEY_REGEX],
        ], [
            'required' => 'Informe o saldo inicial para abrir o caixa.',
            'regex' => 'Informe um valor numérico válido, ex.: 200,00.',
        ]);

        $this->status = 'aberto';
        $this->openingBalance = (float) str_replace(',', '.', $this->openingBalanceInput);
        $this->openedAt = now()->format('H:i');
        $this->movements = [];
        $this->feedback = [
            'variant' => 'success',
            'title' => 'Caixa aberto',
            'message' => "Saldo inicial de R$ {$this->formatMoney($this->openingBalance)} registrado. Movimentações liberadas.",
        ];
    }

    public function registerMovement(): void
    {
        if ($this->status !== 'aberto') {
            $this->feedback = [
                'variant' => 'danger',
                'title' => 'Caixa fechado',
                'message' => 'Não é possível registrar movimentação com o caixa fechado (RN-084).',
            ];

            return;
        }

        $this->validate([
            'movementType' => ['required', Rule::in(['entrada', 'saida', 'estorno'])],
            'movementAmount' => ['required', 'regex:'.self::MONEY_REGEX],
            'movementMethod' => ['required', Rule::in(['dinheiro', 'pix', 'cartao'])],
            'movementDescription' => ['required', 'string', 'max:160'],
        ], [
            'required' => 'Preencha este campo para registrar a movimentação.',
            'regex' => 'Informe um valor numérico válido, ex.: 25,00.',
        ]);

        if ((float) str_replace(',', '.', $this->movementAmount) <= 0) {
            $this->addError('movementAmount', 'O valor deve ser maior que zero.');

            return;
        }

        $this->movements[] = [
            'id' => count($this->movements) ? max(array_column($this->movements, 'id')) + 1 : 1,
            'time' => now()->format('H:i'),
            'type' => $this->movementType,
            'amount' => (float) str_replace(',', '.', $this->movementAmount),
            'method' => $this->movementMethod,
            'description' => $this->movementDescription,
            'protocol' => null,
            'operator' => $this->operator,
        ];

        $this->reset(['movementAmount', 'movementDescription']);
        $this->movementType = 'entrada';
        $this->movementMethod = 'dinheiro';
        $this->feedback = [
            'variant' => 'success',
            'title' => 'Movimentação registrada',
            'message' => 'O lançamento manual foi adicionado ao caixa aberto.',
        ];
    }

    public function closeRegister(): void
    {
        $this->validate([
            'informedAmount' => ['required', 'regex:'.self::MONEY_REGEX],
        ], [
            'required' => 'Informe o total conferido para fechar o caixa.',
            'regex' => 'Informe um valor numérico válido, ex.: 235,00.',
        ]);

        $informed = (float) str_replace(',', '.', $this->informedAmount);
        $expected = $this->expectedBalance();
        $difference = round($informed - $expected, 2);

        $this->closedSessions[] = [
            'id' => count($this->closedSessions) ? max(array_column($this->closedSessions, 'id')) + 1 : 1,
            'terminal' => $this->terminal,
            'operator' => $this->operator,
            'period' => now()->format('d/m/Y').' '.$this->openedAt.' às '.now()->format('H:i'),
            'opening' => $this->openingBalance,
            'expected' => $expected,
            'informed' => $informed,
            'difference' => $difference,
            'status' => abs($difference) < 0.01 ? 'conferido' : 'diferenca',
        ];

        $this->status = 'fechado';
        $this->movements = [];
        $this->reset(['informedAmount', 'closingNotes']);
        $this->feedback = [
            'variant' => abs($difference) < 0.01 ? 'success' : 'warning',
            'title' => 'Caixa fechado',
            'message' => abs($difference) < 0.01
                ? 'Conferência sem diferença. Saldo esperado confirmado.'
                : 'Conferência registrada com diferença de R$ '.$this->formatMoney(abs($difference)).'. Histórico preservado para análise.',
        ];
    }

    public function expectedBalance(): float
    {
        $income = collect($this->movements)->where('type', 'entrada')->sum('amount');
        $outflow = collect($this->movements)->whereIn('type', ['saida', 'estorno'])->sum('amount');

        return round($this->openingBalance + $income - $outflow, 2);
    }

    public function incomeTotal(): float
    {
        return round(collect($this->movements)->where('type', 'entrada')->sum('amount'), 2);
    }

    public function outflowTotal(): float
    {
        return round(collect($this->movements)->whereIn('type', ['saida', 'estorno'])->sum('amount'), 2);
    }

    public function cancellationsTotal(): float
    {
        return round(collect($this->movements)->where('type', 'estorno')->sum('amount'), 2);
    }

    private function formatMoney(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    public function render(): View
    {
        return view('livewire.cash-register')
            ->layout('components.layouts.app', [
                'title' => 'Caixa',
                'heading' => 'Caixa',
                'headingDescription' => 'Abertura, movimentações, conferência e fechamento — protótipo demonstrativo',
            ]);
    }
}
