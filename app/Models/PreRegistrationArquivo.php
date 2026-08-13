<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'implantacao_id', 'pre_registration_id', 'arquivo_id', 'category',
    'is_current', 'linked_at', 'replaced_at',
])]
class PreRegistrationArquivo extends Model
{
    use BelongsToImplantacao, HasUuids;

    protected $table = 'pre_registration_arquivos';

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'linked_at' => 'datetime',
            'replaced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PreRegistration, $this> */
    public function preRegistration(): BelongsTo
    {
        return $this->belongsTo(PreRegistration::class);
    }

    /** @return BelongsTo<Arquivo, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(Arquivo::class, 'arquivo_id');
    }
}
