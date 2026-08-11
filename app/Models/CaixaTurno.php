<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// implantacao_id é fillable pelo mesmo motivo documentado em PreRegistration.
#[Fillable([
    'implantacao_id', 'terminal', 'operator_id', 'opened_at', 'opening_balance',
    'closed_at', 'informed_amount', 'expected_amount', 'difference',
    'closing_notes', 'status', 'closing_status',
])]
class CaixaTurno extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'caixa_turnos';

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_balance' => 'decimal:2',
            'informed_amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /** @return HasMany<CaixaMovimentacao, $this> */
    public function movimentacoes(): HasMany
    {
        return $this->hasMany(CaixaMovimentacao::class);
    }

    public function incomeTotal(): float
    {
        return (float) $this->movimentacoes()->where('type', 'entrada')->sum('amount');
    }

    public function outflowTotal(): float
    {
        return (float) $this->movimentacoes()->whereIn('type', ['saida', 'estorno'])->sum('amount');
    }

    public function cancellationsTotal(): float
    {
        return (float) $this->movimentacoes()->where('type', 'estorno')->sum('amount');
    }

    public function expectedBalance(): float
    {
        return round((float) $this->opening_balance + $this->incomeTotal() - $this->outflowTotal(), 2);
    }
}
