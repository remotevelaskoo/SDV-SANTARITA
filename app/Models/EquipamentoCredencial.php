<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Credencial técnica do equipamento (ADR-009). Guarda onde o segredo está
 * (`env:NOME`) ou, com a referência `cifrado:BANCO`, o valor cifrado com a
 * chave da aplicação, nunca em texto claro. Nada disso é serializado para o
 * frontend; o valor só é decifrado pelo ResolvedorSegredo no momento do uso.
 */
#[Fillable([
    'implantacao_id', 'equipamento_id', 'finalidade', 'usuario_tecnico',
    'referencia_segredo', 'segredo_cifrado', 'status', 'definida_em', 'substituida_em', 'created_by',
])]
#[Hidden(['referencia_segredo', 'segredo_cifrado', 'usuario_tecnico'])]
class EquipamentoCredencial extends Model
{
    use BelongsToImplantacao, HasUuids;

    public const REFERENCIA_CIFRADA = 'cifrado:BANCO';

    protected $table = 'equipamento_credenciais';

    protected function casts(): array
    {
        return [
            'definida_em' => 'datetime',
            'substituida_em' => 'datetime',
            'segredo_cifrado' => 'encrypted',
        ];
    }

    public function cifrada(): bool
    {
        return $this->referencia_segredo === self::REFERENCIA_CIFRADA;
    }

    /** @return BelongsTo<Equipamento, $this> */
    public function equipamento(): BelongsTo
    {
        return $this->belongsTo(Equipamento::class);
    }
}
