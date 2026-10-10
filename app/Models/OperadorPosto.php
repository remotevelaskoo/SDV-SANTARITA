<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Operador designado para um posto, com início e fim (ADR-017). Trocar o
 * operador de função encerra a designação; nada concede acesso global aos
 * equipamentos.
 */
#[Fillable(['implantacao_id', 'user_id', 'posto_id', 'started_at', 'ended_at', 'created_by'])]
class OperadorPosto extends Model
{
    use BelongsToImplantacao, HasUuids;

    protected $table = 'operador_postos';

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Posto, $this> */
    public function posto(): BelongsTo
    {
        return $this->belongsTo(Posto::class);
    }
}
