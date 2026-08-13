<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'implantacao_id', 'disk', 'object_key', 'original_name', 'display_name',
    'extension', 'declared_mime', 'detected_mime', 'size_bytes',
    'checksum_sha256', 'classification', 'purpose', 'status', 'origin',
    'created_by', 'retention_rule', 'retention_eligible_at', 'legal_hold',
    'error_message',
])]
class Arquivo extends Model
{
    use BelongsToImplantacao, HasUuids;

    protected $table = 'arquivos';

    protected static function booted(): void
    {
        static::updating(function (Arquivo $arquivo): void {
            if ($arquivo->isDirty([
                'disk', 'object_key', 'original_name', 'extension', 'declared_mime',
                'detected_mime', 'size_bytes', 'checksum_sha256', 'classification',
                'purpose', 'origin', 'created_by',
            ])) {
                throw new LogicException('O objeto de um arquivo não pode ser sobrescrito. Crie uma nova versão.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'original_name' => 'encrypted',
            'size_bytes' => 'integer',
            'retention_eligible_at' => 'datetime',
            'legal_hold' => 'boolean',
        ];
    }

    /** @return HasMany<PreRegistrationArquivo, $this> */
    public function preRegistrationLinks(): HasMany
    {
        return $this->hasMany(PreRegistrationArquivo::class, 'arquivo_id');
    }

    public function isAvailable(): bool
    {
        return $this->status === 'disponivel';
    }
}
