<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * O que vai ao terminal para uma autorização (AJ-006). O `id` é o UUID
 * interno usado na sincronização; o identificador do fabricante fica em
 * `referencias_externas` e nunca o substitui.
 */
#[Fillable([
    'implantacao_id', 'pessoa_id', 'autorizacao_id', 'tipo', 'vigencia_inicio', 'vigencia_fim', 'status', 'revogada_em',
])]
class CredencialAcesso extends Model
{
    use BelongsToImplantacao, HasUuids;

    /** Cadastro da pessoa no terminal, com vigência. Sem foto nem template (ADR-013). */
    public const TIPO_CADASTRO = 'cadastro_pessoa';

    protected $table = 'credenciais_acesso';

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Credencial de acesso não é apagada; revogue.'));
    }

    protected function casts(): array
    {
        return [
            'vigencia_inicio' => 'datetime',
            'vigencia_fim' => 'datetime',
            'revogada_em' => 'datetime',
        ];
    }

    /** @return BelongsTo<AutorizacaoAcesso, $this> */
    public function autorizacao(): BelongsTo
    {
        return $this->belongsTo(AutorizacaoAcesso::class, 'autorizacao_id');
    }

    /** @return HasMany<CredencialSincronizacao, $this> */
    public function sincronizacoes(): HasMany
    {
        return $this->hasMany(CredencialSincronizacao::class, 'credencial_id');
    }
}
