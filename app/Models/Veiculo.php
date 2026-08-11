<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// implantacao_id é fillable pelo mesmo motivo documentado em PreRegistration.
#[Fillable([
    'implantacao_id', 'plate_display', 'plate_normalized', 'country',
    'type', 'brand', 'model', 'color', 'status',
])]
class Veiculo extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'veiculos';

    public static function normalizePlate(string $plate): string
    {
        return strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $plate));
    }

    /** @return HasMany<VeiculoVinculo, $this> */
    public function vinculos(): HasMany
    {
        return $this->hasMany(VeiculoVinculo::class);
    }

    public function proprietario(): ?Pessoa
    {
        $vinculo = $this->vinculos()
            ->where('tipo', 'proprietario')
            ->whereNull('ended_at')
            ->with('pessoa')
            ->first();

        return $vinculo?->pessoa;
    }
}
