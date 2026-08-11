<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// implantacao_id é fillable pelo mesmo motivo documentado em PreRegistration.
#[Fillable([
    'implantacao_id',
    'pre_registration_id', 'action', 'field', 'old_value', 'new_value',
    'reason', 'result', 'operator_id', 'operator_name', 'occurred_at',
])]
class PreRegistrationEdit extends Model
{
    use BelongsToImplantacao, HasUuids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PreRegistration, $this> */
    public function preRegistration(): BelongsTo
    {
        return $this->belongsTo(PreRegistration::class);
    }

    /** @return BelongsTo<User, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
