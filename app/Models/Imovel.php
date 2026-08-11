<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// implantacao_id é fillable pelo mesmo motivo documentado em PreRegistration.
#[Fillable([
    'implantacao_id', 'condominio_id', 'bloco_id',
    'codigo', 'unidade', 'tipo', 'status', 'versao',
    'created_by', 'updated_by',
])]
class Imovel extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'imoveis';

    protected function casts(): array
    {
        return [
            'versao' => 'integer',
        ];
    }

    /** @return BelongsTo<Condominio, $this> */
    public function condominio(): BelongsTo
    {
        return $this->belongsTo(Condominio::class);
    }

    /** @return BelongsTo<Bloco, $this> */
    public function bloco(): BelongsTo
    {
        return $this->belongsTo(Bloco::class);
    }

    /** @return HasMany<EnderecoImovel, $this> */
    public function enderecos(): HasMany
    {
        return $this->hasMany(EnderecoImovel::class)->orderByDesc('started_at');
    }

    public function enderecoVigente(): ?EnderecoImovel
    {
        return $this->enderecos()->whereNull('ended_at')->first();
    }

    public function label(): string
    {
        return $this->bloco !== null
            ? "{$this->bloco->nome} — {$this->unidade}"
            : $this->codigo;
    }
}
