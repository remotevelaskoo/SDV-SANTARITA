<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['chave', 'modulo', 'descricao'])]
class Permissao extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'permissoes';

    /** @return HasMany<PerfilPermissao, $this> */
    public function perfilPermissoes(): HasMany
    {
        return $this->hasMany(PerfilPermissao::class);
    }
}
