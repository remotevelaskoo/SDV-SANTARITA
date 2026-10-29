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

    /** Lançamento só entra em caixa aberto e não é editado nem excluído depois (RN-048, AJ-008). */
    protected static function booted(): void
    {
        static::creating(function (self $movimentacao): void {
            $turno = CaixaTurno::query()->find($movimentacao->caixa_turno_id);
            if ($turno === null || $turno->status !== 'aberto') {
                throw new \LogicException('Movimentação exige caixa aberto (RN-084).');
            }
        });

        static::updating(fn () => throw new \LogicException('Movimentação de caixa não é editada; registre um estorno.'));
        static::deleting(fn () => throw new \LogicException('Movimentação de caixa não é excluída; registre um estorno.'));
    }

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
