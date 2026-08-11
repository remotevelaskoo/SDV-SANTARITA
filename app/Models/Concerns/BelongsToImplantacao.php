<?php

namespace App\Models\Concerns;

use App\Models\Implantacao;
use App\Models\Scopes\ImplantacaoScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marca uma entidade operacional como pertencente a uma implantação
 * (ADR-002, seção 8.2). Aplica um global scope que restringe toda consulta
 * ao contexto ativo e atribui `implantacao_id` pelo servidor na criação,
 * ignorando qualquer valor vindo de mass assignment (ADR-002, seção 13.2).
 */
trait BelongsToImplantacao
{
    public static function bootBelongsToImplantacao(): void
    {
        static::addGlobalScope(new ImplantacaoScope);

        static::creating(function ($model): void {
            $model->implantacao_id = Implantacao::current()->id;
        });
    }

    /** @return BelongsTo<Implantacao, $this> */
    public function implantacao(): BelongsTo
    {
        return $this->belongsTo(Implantacao::class);
    }
}
