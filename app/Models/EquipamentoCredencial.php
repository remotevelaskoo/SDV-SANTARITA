<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Referência à credencial técnica do equipamento (ADR-009). Guarda somente
 * onde o segredo está (`env:NOME` ou `vault:caminho`), nunca o valor. Mesmo
 * a referência fica fora da serialização para o frontend.
 */
#[Fillable([
    'implantacao_id', 'equipamento_id', 'finalidade', 'usuario_tecnico',
    'referencia_segredo', 'status', 'definida_em', 'substituida_em', 'created_by',
])]
#[Hidden(['referencia_segredo', 'usuario_tecnico'])]
class EquipamentoCredencial extends Model
{
    use BelongsToImplantacao, HasUuids;

    protected $table = 'equipamento_credenciais';

    protected function casts(): array
    {
        return [
            'definida_em' => 'datetime',
            'substituida_em' => 'datetime',
        ];
    }

    /** @return BelongsTo<Equipamento, $this> */
    public function equipamento(): BelongsTo
    {
        return $this->belongsTo(Equipamento::class);
    }
}
