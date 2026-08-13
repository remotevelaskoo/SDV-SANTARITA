<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['auditoria_evento_id', 'ip_address', 'user_agent', 'request_method', 'request_path', 'metadata'])]
class AuditoriaContexto extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'auditoria_contextos';

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Registros de auditoria são imutáveis.'));
        static::deleting(fn () => throw new LogicException('Registros de auditoria são imutáveis.'));
    }

    protected function casts(): array
    {
        return ['metadata' => 'json'];
    }

    /** @return BelongsTo<AuditoriaEvento, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(AuditoriaEvento::class, 'auditoria_evento_id');
    }
}
