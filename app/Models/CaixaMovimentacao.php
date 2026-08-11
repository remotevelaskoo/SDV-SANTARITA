<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// implantacao_id é fillable pelo mesmo motivo documentado em PreRegistration.
#[Fillable([
    'implantacao_id', 'caixa_turno_id', 'type', 'amount', 'method',
    'description', 'protocol', 'operator_id', 'occurred_at',
])]
class CaixaMovimentacao extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'caixa_movimentacoes';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CaixaTurno, $this> */
    public function caixaTurno(): BelongsTo
    {
        return $this->belongsTo(CaixaTurno::class);
    }

    /** @return BelongsTo<User, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
