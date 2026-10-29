<?php

namespace App\Models;

use App\Integracoes\Dominio\Enums\Direcao;
use App\Integracoes\Dominio\Enums\StatusPontoAcesso;
use App\Integracoes\Dominio\Enums\TipoPontoAcesso;
use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'implantacao_id', 'codigo', 'nome', 'tipo', 'direcao_suportada', 'localizacao',
    'status', 'observacao', 'versao', 'created_by', 'updated_by', 'inactivated_at',
])]
class PontoAcesso extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'pontos_acesso';

    protected function casts(): array
    {
        return [
            'tipo' => TipoPontoAcesso::class,
            'direcao_suportada' => Direcao::class,
            'status' => StatusPontoAcesso::class,
            'versao' => 'integer',
            'inactivated_at' => 'datetime',
        ];
    }

    /** @return HasMany<EquipamentoPontoVinculo, $this> */
    public function vinculosEquipamento(): HasMany
    {
        return $this->hasMany(EquipamentoPontoVinculo::class)->orderByDesc('started_at');
    }

    /** @return HasOne<EquipamentoPontoVinculo, $this> */
    public function vinculoVigente(): HasOne
    {
        return $this->hasOne(EquipamentoPontoVinculo::class)->whereNull('ended_at');
    }
}
