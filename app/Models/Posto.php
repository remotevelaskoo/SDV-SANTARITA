<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Posto ou guarita da portaria (ADR-017). Define quais pontos de acesso, e
 * portanto quais terminais, um operador pode acionar na liberação manual.
 */
#[Fillable(['implantacao_id', 'codigo', 'nome', 'status', 'created_by'])]
class Posto extends Model
{
    use BelongsToImplantacao, HasUuids;

    protected $table = 'postos';

    /** @return HasMany<PostoPontoAcesso, $this> */
    public function pontosVinculados(): HasMany
    {
        return $this->hasMany(PostoPontoAcesso::class);
    }

    /** @return BelongsToMany<PontoAcesso, $this> */
    public function pontosVigentes(): BelongsToMany
    {
        return $this->belongsToMany(PontoAcesso::class, 'posto_pontos_acesso')
            ->wherePivotNull('ended_at');
    }

    /** @return HasMany<OperadorPosto, $this> */
    public function operadores(): HasMany
    {
        return $this->hasMany(OperadorPosto::class);
    }

    public function ativo(): bool
    {
        return $this->status === 'ativo';
    }
}
