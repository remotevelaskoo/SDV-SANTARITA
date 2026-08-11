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
    'implantacao_id', 'pessoa_id', 'imovel_id', 'veiculo_id',
    'ponto_acesso', 'tipo', 'resultado', 'motivo_negacao',
    'operator_id', 'protocol', 'notes', 'occurred_at',
])]
class HistoricoAcesso extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'historico_acessos';

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
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

    /** @return BelongsTo<Veiculo, $this> */
    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    /** @return BelongsTo<User, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
