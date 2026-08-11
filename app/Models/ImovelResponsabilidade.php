<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// implantacao_id é fillable pelo mesmo motivo documentado em PreRegistration.
#[Fillable(['implantacao_id', 'imovel_id', 'vinculo_id', 'tipo', 'started_at', 'ended_at'])]
class ImovelResponsabilidade extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'imovel_responsabilidades';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Imovel, $this> */
    public function imovel(): BelongsTo
    {
        return $this->belongsTo(Imovel::class);
    }

    /** @return BelongsTo<Vinculo, $this> */
    public function vinculo(): BelongsTo
    {
        return $this->belongsTo(Vinculo::class);
    }
}
