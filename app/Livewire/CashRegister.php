<?php

namespace App\Livewire;

use App\Models\CaixaMovimentacao;
use App\Models\CaixaTurno;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CashRegister extends Component
{
    public string $status = 'fechado';

    public string $terminal = 'Caixa 01 — Guarita';

    public ?string $operator = null;

    public ?string $openedAt = null;

    public float $openingBalance = 0.0;

    public ?string $currentTurnoId = null;

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

    public function mount(): void
    {
        $this->syncCurrentTurno();
    }

    public function openRegister(): void
    {
        $this->validate([
            'openingBalanceInput' => ['required', 'regex:'.self::MONEY_REGEX],
        ], [
            'required' => 'Informe o saldo inicial para abrir o caixa.',
            'regex' => 'Informe um valor numérico válido, ex.: 200,00.',
        ]);

        $openingBalance = (float) str_replace(',', '.', $this->openingBalanceInput);

        CaixaTurno::query()->create([
            'terminal' => $this->terminal,
            'operator_id' => Auth::id(),
            'opened_at' => now(),
            'opening_balance' => $openingBalance,
            'status' => 'aberto',
        ]);

        $this->syncCurrentTurno();
        $this->feedback = [
            'variant' => 'success',
            'title' => 'Caixa aberto',
            'message' => "Saldo inicial de R$ {$this->formatMoney($openingBalance)} registrado. Movimentações liberadas.",
        ];
    }

    public function registerMovement(): void
    {
        $turno = $this->currentTurno();

        if (! $turno) {
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

        CaixaMovimentacao::query()->create([
            'caixa_turno_id' => $turno->id,
            'type' => $this->movementType,
            'amount' => (float) str_replace(',', '.', $this->movementAmount),
            'method' => $this->movementMethod,
            'description' => $this->movementDescription,
            'operator_id' => Auth::id(),
            'occurred_at' => now(),
        ]);

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
        $turno = $this->currentTurno();

        if (! $turno) {
            return;
        }

        $this->validate([
            'informedAmount' => ['required', 'regex:'.self::MONEY_REGEX],
        ], [
            'required' => 'Informe o total conferido para fechar o caixa.',
            'regex' => 'Informe um valor numérico válido, ex.: 235,00.',
        ]);

        $informed = (float) str_replace(',', '.', $this->informedAmount);
        $expected = $turno->expectedBalance();
        $difference = round($informed - $expected, 2);

        $turno->update([
            'closed_at' => now(),
            'informed_amount' => $informed,
            'expected_amount' => $expected,
            'difference' => $difference,
            'closing_notes' => $this->closingNotes !== '' ? $this->closingNotes : null,
            'status' => 'fechado',
            'closing_status' => abs($difference) < 0.01 ? 'conferido' : 'diferenca',
        ]);

        $this->syncCurrentTurno();
        $this->reset(['informedAmount', 'closingNotes']);
        $this->feedback = [
            'variant' => abs($difference) < 0.01 ? 'success' : 'warning',
            'title' => 'Caixa fechado',
            'message' => abs($difference) < 0.01
                ? 'Conferência sem diferença. Saldo esperado confirmado.'
                : 'Conferência registrada com diferença de R$ '.$this->formatMoney(abs($difference)).'. Histórico preservado para análise.',
        ];
    }

    public function incomeTotal(): float
    {
        return $this->currentTurno()?->incomeTotal() ?? 0.0;
    }

    public function outflowTotal(): float
    {
        return $this->currentTurno()?->outflowTotal() ?? 0.0;
    }

    public function cancellationsTotal(): float
    {
        return $this->currentTurno()?->cancellationsTotal() ?? 0.0;
    }

    public function expectedBalance(): float
    {
        return $this->currentTurno()?->expectedBalance() ?? 0.0;
    }

    private function currentTurno(): ?CaixaTurno
    {
        return $this->currentTurnoId ? CaixaTurno::query()->find($this->currentTurnoId) : null;
    }

    private function syncCurrentTurno(): void
    {
        $turno = CaixaTurno::query()->where('status', 'aberto')->latest('opened_at')->first();

        if ($turno) {
            $this->currentTurnoId = $turno->id;
            $this->status = 'aberto';
            $this->operator = $turno->operator?->name ?? 'Sistema';
            $this->terminal = $turno->terminal;
            $this->openedAt = $turno->opened_at->format('H:i');
            $this->openingBalance = (float) $turno->opening_balance;
        } else {
            $this->currentTurnoId = null;
            $this->status = 'fechado';
        }
    }

    private function formatMoney(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    public function render(): View
    {
        $turno = $this->currentTurno();

        $movements = $turno
            ? $turno->movimentacoes()->with('operator')->orderBy('occurred_at')->get()
                ->map(fn (CaixaMovimentacao $movimentacao) => [
                    'id' => $movimentacao->id,
                    'time' => $movimentacao->occurred_at->format('H:i'),
                    'type' => $movimentacao->type,
                    'amount' => (float) $movimentacao->amount,
                    'method' => $movimentacao->method,
                    'description' => $movimentacao->description,
                    'protocol' => $movimentacao->protocol,
                    'operator' => $movimentacao->operator?->name ?? 'Sistema',
                ])->values()->all()
            : [];

        $closedSessions = CaixaTurno::query()->where('status', 'fechado')->with('operator')->orderBy('closed_at')->get()
            ->map(fn (CaixaTurno $sessao) => [
                'id' => $sessao->id,
                'terminal' => $sessao->terminal,
                'operator' => $sessao->operator?->name ?? 'Sistema',
                'period' => $sessao->opened_at->format('d/m/Y').' '.$sessao->opened_at->format('H:i').' às '.$sessao->closed_at?->format('H:i'),
                'opening' => (float) $sessao->opening_balance,
                'expected' => (float) $sessao->expected_amount,
                'informed' => (float) $sessao->informed_amount,
                'difference' => (float) $sessao->difference,
                'status' => $sessao->closing_status,
            ])->values()->all();

        return view('livewire.cash-register', [
            'movements' => $movements,
            'closedSessions' => $closedSessions,
        ])->layout('components.layouts.app', [
            'title' => 'Caixa',
            'heading' => 'Caixa',
            'headingDescription' => 'Abertura, movimentações, conferência e fechamento',
        ]);
    }
}
