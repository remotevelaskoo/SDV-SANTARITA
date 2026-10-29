<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Histórico imutável das correções do número do ticket (AJ-006). */
#[Fillable([
    'implantacao_id', 'contribuicao_id', 'valor_anterior', 'valor_novo',
    'justificativa', 'operator_id', 'corrigido_em',
])]
class ContribuicaoTicketCorrecao extends Model
{
    use BelongsToImplantacao, HasUuids;

    protected $table = 'contribuicao_ticket_correcoes';

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Correções de ticket não podem ser alteradas.'));
        static::deleting(fn () => throw new LogicException('Correções de ticket não podem ser excluídas.'));
    }

    protected function casts(): array
    {
        return ['corrigido_em' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
