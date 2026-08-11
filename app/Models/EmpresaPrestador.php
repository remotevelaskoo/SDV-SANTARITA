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
    'implantacao_id', 'empresa_id', 'pessoa_id',
    'atividade', 'status', 'started_at', 'ended_at', 'versao',
])]
class EmpresaPrestador extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'empresa_prestadores';

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
        static::saving(function (self $prestador): void {
            if ($prestador->ended_at !== null && $prestador->ended_at <= $prestador->started_at) {
                throw new InvalidTemporalRangeException;
            }
        });
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return BelongsTo<Pessoa, $this> */
    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }
}
