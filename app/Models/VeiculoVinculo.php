<?php

namespace App\Models;

use App\Exceptions\InvalidTemporalRangeException;
use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// implantacao_id é fillable pelo mesmo motivo documentado em PreRegistration.
#[Fillable([
    'implantacao_id', 'veiculo_id', 'pessoa_id', 'imovel_id',
    'tipo', 'status', 'started_at', 'ended_at', 'versao',
])]
class VeiculoVinculo extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'veiculo_vinculos';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'versao' => 'integer',
        ];
    }

    // Mesma regra de integridade temporal de Vinculo — ver o comentário lá.
    protected static function booted(): void
    {
        static::saving(function (self $vinculo): void {
            if ($vinculo->ended_at !== null && $vinculo->ended_at <= $vinculo->started_at) {
                throw new InvalidTemporalRangeException;
            }
        });
    }

    /** @return BelongsTo<Veiculo, $this> */
    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    /** @return BelongsTo<Pessoa, $this> */
    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    /** @return BelongsTo<Imovel, $this> */
    public function imovel(): BelongsTo
    {
        return $this->belongsTo(Imovel::class);
    }
}
