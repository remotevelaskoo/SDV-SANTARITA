<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

#[Fillable([
    'implantacao_id', 'occurred_at', 'recorded_at', 'actor_type', 'actor_id',
    'actor_name', 'actor_profile', 'session_hash', 'action', 'module',
    'entity_type', 'entity_id', 'origin', 'result', 'reason_code',
    'justification', 'correlation_id', 'causation_id', 'classification',
])]
class AuditoriaEvento extends Model
{
    use BelongsToImplantacao, HasUuids;

    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    protected $table = 'auditoria_eventos';

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Registros de auditoria são imutáveis.'));
        static::deleting(fn () => throw new LogicException('Registros de auditoria são imutáveis.'));
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'recorded_at' => 'datetime',
        ];
    }

    /** @return HasMany<AuditoriaAlteracao, $this> */
    public function changes(): HasMany
    {
        return $this->hasMany(AuditoriaAlteracao::class, 'auditoria_evento_id')->orderBy('field_name');
    }

    /** @return HasOne<AuditoriaContexto, $this> */
    public function context(): HasOne
    {
        return $this->hasOne(AuditoriaContexto::class, 'auditoria_evento_id');
    }
}
