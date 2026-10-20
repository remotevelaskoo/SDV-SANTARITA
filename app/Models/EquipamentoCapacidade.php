<?php

namespace App\Models;

use App\Integracoes\Dominio\Enums\EstadoHomologacao;
use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'implantacao_id', 'equipamento_id', 'capacidade', 'suportada', 'estado_homologacao', 'origem',
    'versao_contrato', 'firmware_versao', 'motivo_ausencia', 'verificada_em',
])]
class EquipamentoCapacidade extends Model
{
    use BelongsToImplantacao, HasUuids;

    protected $table = 'equipamento_capacidades';

    protected function casts(): array
    {
        return [
            'suportada' => 'boolean',
            'estado_homologacao' => EstadoHomologacao::class,
            'verificada_em' => 'datetime',
        ];
    }
}
