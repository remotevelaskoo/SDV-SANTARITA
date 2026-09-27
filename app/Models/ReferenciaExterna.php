<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Identificador de fabricante, sempre secundário ao UUID interno (RN-090). */
#[Fillable([
    'implantacao_id', 'adaptador', 'equipamento_id', 'entidade_tipo', 'entidade_id',
    'tipo_externo', 'id_externo', 'substituida_em',
])]
class ReferenciaExterna extends Model
{
    use BelongsToImplantacao, HasUuids;

    protected $table = 'referencias_externas';

    protected function casts(): array
    {
        return [
            'substituida_em' => 'datetime',
        ];
    }
}
