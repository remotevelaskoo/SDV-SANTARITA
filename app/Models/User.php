<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'username', 'password', 'can_edit_pre_registrations'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'can_edit_pre_registrations' => 'boolean',
        ];
    }

    /** @return HasMany<UsuarioImplantacao, $this> */
    public function implantacoes(): HasMany
    {
        return $this->hasMany(UsuarioImplantacao::class);
    }

    /** @return HasMany<UsuarioPerfil, $this> */
    public function usuarioPerfis(): HasMany
    {
        return $this->hasMany(UsuarioPerfil::class);
    }

    /**
     * Verifica se o usuário tem a permissão via algum perfil vigente,
     * dentro da implantação atual (App\Support\ImplantacaoContext).
     * Não substitui `can_edit_pre_registrations`, que continua sendo a
     * checagem usada por PreRegistrationPolicy — reconciliar os dois
     * fica para quando houver tela de gestão de perfis (P19/P20).
     */
    public function hasPermission(string $chave): bool
    {
        return $this->usuarioPerfis()
            ->whereNull('ended_at')
            ->whereHas('perfil', fn ($query) => $query->whereHas('permissoes', fn ($query) => $query->where('chave', $chave)))
            ->exists();
    }
}
