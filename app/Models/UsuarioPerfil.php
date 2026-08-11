<?php

namespace App\Models;

use App\Exceptions\InvalidTemporalRangeException;
use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// implantacao_id é fillable pelo mesmo motivo documentado em PreRegistration.
#[Fillable(['implantacao_id', 'user_id', 'perfil_id', 'started_at', 'ended_at'])]
class UsuarioPerfil extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'usuario_perfis';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    // Mesma regra de integridade temporal de Vinculo — ver o comentário lá.
    protected static function booted(): void
    {
        static::saving(function (self $usuarioPerfil): void {
            if ($usuarioPerfil->ended_at !== null && $usuarioPerfil->ended_at <= $usuarioPerfil->started_at) {
                throw new InvalidTemporalRangeException;
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Perfil, $this> */
    public function perfil(): BelongsTo
    {
        return $this->belongsTo(Perfil::class);
    }

    public function vigente(): bool
    {
        return $this->ended_at === null;
    }
}
