<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// implantacao_id é fillable pelo mesmo motivo documentado em PreRegistration.
#[Fillable([
    'implantacao_id', 'imovel_id',
    'zip_code', 'address', 'address_number', 'address_complement', 'district', 'city', 'state',
    'started_at', 'ended_at',
])]
class EnderecoImovel extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'enderecos_imoveis';

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
}
