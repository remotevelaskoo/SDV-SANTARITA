<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'implantacao_id', 'equipamento_id', 'id_externo_evento', 'hash_payload', 'tipo',
    'direcao', 'resultado_equipamento', 'referencia_credencial_externa',
    'ocorrido_no_equipamento_em', 'recebido_em', 'divergencia_relogio_segundos',
    'dados_sanitizados', 'correlation_id',
])]
class EquipamentoEventoRecebido extends Model
{
    use BelongsToImplantacao, HasUuids;

    protected $table = 'equipamento_eventos_recebidos';

    protected function casts(): array
    {
        return [
            'ocorrido_no_equipamento_em' => 'datetime',
            'recebido_em' => 'datetime',
            'divergencia_relogio_segundos' => 'integer',
            'dados_sanitizados' => 'array',
        ];
    }
}
