<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'implantacao_id', 'equipamento_id', 'operacao_integracao_id', 'tipo', 'resultado',
    'codigo', 'mensagem_sanitizada', 'latencia_ms', 'correlation_id', 'ocorrido_em',
])]
class IntegracaoOcorrencia extends Model
{
    use BelongsToImplantacao, HasUuids;

    protected $table = 'integracao_ocorrencias';

    protected function casts(): array
    {
        return [
            'latencia_ms' => 'integer',
            'ocorrido_em' => 'datetime',
        ];
    }
}
