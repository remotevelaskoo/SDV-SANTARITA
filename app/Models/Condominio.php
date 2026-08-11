<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// implantacao_id é fillable pelo mesmo motivo documentado em PreRegistration.
#[Fillable(['implantacao_id', 'nome', 'codigo', 'status'])]
class Condominio extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'condominios';

    /** @return HasMany<Bloco, $this> */
    public function blocos(): HasMany
    {
        return $this->hasMany(Bloco::class);
    }

    /** @return HasMany<Imovel, $this> */
    public function imoveis(): HasMany
    {
        return $this->hasMany(Imovel::class);
    }
}
