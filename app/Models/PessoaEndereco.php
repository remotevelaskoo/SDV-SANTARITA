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
    'implantacao_id', 'pessoa_id', 'finalidade',
    'zip_code', 'address', 'address_number', 'address_complement', 'district', 'city', 'state',
    'started_at', 'ended_at',
])]
class PessoaEndereco extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'pessoa_enderecos';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Pessoa, $this> */
    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }
}
