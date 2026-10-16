<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'implantacao_id', 'pre_registration_id', 'document_type',
    'engine', 'model_version', 'status',
    'extracted_fields', 'field_confidences', 'confirmed_fields',
    'processed_at', 'confirmed_at',
])]
class PreRegistrationDocumentExtraction extends Model
{
    use BelongsToImplantacao, HasUuids;

    protected function casts(): array
    {
        return [
            'extracted_fields' => 'encrypted:array',
            'field_confidences' => 'encrypted:array',
            'confirmed_fields' => 'encrypted:array',
            'processed_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PreRegistration, $this> */
    public function preRegistration(): BelongsTo
    {
        return $this->belongsTo(PreRegistration::class);
    }
}
