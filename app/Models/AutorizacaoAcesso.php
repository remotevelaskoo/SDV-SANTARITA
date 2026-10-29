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
 * Decisão do SDV que permite o acesso de uma pessoa num período (AJ-006).
 * Nunca é apagada: encerrar, expirar ou cancelar só muda a situação.
 */
#[Fillable([
    'implantacao_id', 'pessoa_id', 'vinculo_id', 'imovel_id', 'tipo', 'inicio', 'fim', 'status',
    'responsavel', 'created_by', 'encerrada_em', 'motivo_encerramento', 'encerrada_por', 'versao',
])]
class AutorizacaoAcesso extends Model
{
    use BelongsToImplantacao, HasUuids;

    public const TIPO_VINCULO = 'vinculo';

    public const TIPO_TURISTA = 'turista_24h';

    protected $table = 'autorizacoes_acesso';

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Autorização de acesso não é apagada; encerre ou cancele.'));
    }

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fim' => 'datetime',
            'encerrada_em' => 'datetime',
            'versao' => 'integer',
        ];
    }

    public function vigente(): bool
    {
        return $this->status === 'ativa' && ($this->fim === null || $this->fim->isFuture());
    }

    /** @return BelongsTo<Pessoa, $this> */
    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    /** @return HasMany<CredencialAcesso, $this> */
    public function credenciais(): HasMany
    {
        return $this->hasMany(CredencialAcesso::class, 'autorizacao_id');
    }
}
