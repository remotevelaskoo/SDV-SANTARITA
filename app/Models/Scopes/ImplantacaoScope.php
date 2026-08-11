<?php

namespace App\Models\Scopes;

use App\Support\ImplantacaoContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Impede que consultas operacionais aconteçam sem contexto de implantação
 * (ADR-002, seção 14: "consultas operacionais não deverão ocorrer sem contexto").
 *
 * Acesso global excepcional deve usar `Model::withoutGlobalScope(ImplantacaoScope::class)`
 * explicitamente, nunca removendo a proteção por padrão.
 */
class ImplantacaoScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->getTable().'.implantacao_id', ImplantacaoContext::current()->id);
    }
}
