<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'implantacao_id', 'equipamento_id', 'ponto_acesso_id', 'started_at', 'ended_at',
    'motivo_encerramento', 'created_by', 'ended_by',
])]
class EquipamentoPontoVinculo extends Model
{
    use BelongsToImplantacao, HasUuids;

    protected $table = 'equipamento_ponto_vinculos';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Equipamento, $this> */
    public function equipamento(): BelongsTo
    {
        return $this->belongsTo(Equipamento::class);
    }

    /** @return BelongsTo<PontoAcesso, $this> */
    public function pontoAcesso(): BelongsTo
    {
        return $this->belongsTo(PontoAcesso::class);
    }
}
