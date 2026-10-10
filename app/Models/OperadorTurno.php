<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Posto assumido pelo operador no turno (SDV-OPE-018 §2): início, término e
 * motivo do término. Um turno ativo por operador e implantação.
 */
#[Fillable([
    'implantacao_id', 'user_id', 'posto_id', 'turno_anterior_id',
    'iniciado_em', 'encerrado_em', 'motivo_encerramento',
])]
class OperadorTurno extends Model
{
    use BelongsToImplantacao, HasUuids;

    protected $table = 'operador_turnos';

    protected function casts(): array
    {
        return ['iniciado_em' => 'datetime', 'encerrado_em' => 'datetime'];
    }

    /** @return BelongsTo<Posto, $this> */
    public function posto(): BelongsTo
    {
        return $this->belongsTo(Posto::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
