<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'username', 'password', 'status', 'status_reason', 'status_changed_by', 'status_changed_at', 'invited_at'])]
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
            'status_changed_at' => 'datetime',
            'invited_at' => 'datetime',
        ];
    }

    /** @return HasMany<UsuarioImplantacao, $this> */
    public function implantacoes(): HasMany
    {
        return $this->hasMany(UsuarioImplantacao::class);
    }

    /** @return BelongsTo<User, $this> */
    public function statusChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }

    /** @return HasMany<UsuarioPerfil, $this> */
    public function usuarioPerfis(): HasMany
    {
        return $this->hasMany(UsuarioPerfil::class);
    }

    /**
     * Verifica se o usuário tem a permissão via algum perfil vigente,
     * dentro da implantação atual (App\Support\ImplantacaoContext).
     */
    public function hasPermission(string $chave): bool
    {
        return $this->usuarioPerfis()
            ->whereNull('ended_at')
            ->whereHas('perfil', fn ($query) => $query->whereHas('permissoes', fn ($query) => $query->where('chave', $chave)))
            ->exists();
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    public function operationalRoleLabel(): string
    {
        return $this->usuarioPerfis()
            ->whereNull('ended_at')
            ->with('perfil:id,nome')
            ->get()
            ->pluck('perfil.nome')
            ->filter()
            ->unique()
            ->implode(', ') ?: 'Usuário sem perfil';
    }
}
