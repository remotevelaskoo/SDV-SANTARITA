<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// implantacao_id é fillable pelo mesmo motivo documentado em PreRegistration.
#[Fillable(['implantacao_id', 'condominio_id', 'nome', 'codigo', 'ordem', 'status'])]
class Bloco extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'blocos';

    /** @return BelongsTo<Condominio, $this> */
    public function condominio(): BelongsTo
    {
        return $this->belongsTo(Condominio::class);
    }

    /** @return HasMany<Imovel, $this> */
    public function imoveis(): HasMany
    {
        return $this->hasMany(Imovel::class);
    }
}
