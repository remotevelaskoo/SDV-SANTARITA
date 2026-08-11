<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// implantacao_id é fillable pelo mesmo motivo documentado em PreRegistration.
#[Fillable(['implantacao_id', 'nome', 'status'])]
class Perfil extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'perfis';

    /** @return HasMany<PerfilPermissao, $this> */
    public function perfilPermissoes(): HasMany
    {
        return $this->hasMany(PerfilPermissao::class);
    }

    /** @return BelongsToMany<Permissao, $this> */
    public function permissoes(): BelongsToMany
    {
        return $this->belongsToMany(Permissao::class, 'perfil_permissoes', 'perfil_id', 'permissao_id')
            ->using(PerfilPermissao::class)
            ->withTimestamps();
    }

    /** @return HasMany<UsuarioPerfil, $this> */
    public function usuarioPerfis(): HasMany
    {
        return $this->hasMany(UsuarioPerfil::class);
    }

    public function concede(string $chavePermissao): bool
    {
        return $this->permissoes()->where('chave', $chavePermissao)->exists();
    }
}
