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
    'implantacao_id', 'protocol', 'recipient_name', 'imovel_id',
    'carrier', 'type', 'storage_location', 'status',
    'received_at', 'received_by', 'notified_at', 'delivered_at', 'delivered_to', 'notes',
])]
class Encomenda extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'encomendas';

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'notified_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Imovel, $this> */
    public function imovel(): BelongsTo
    {
        return $this->belongsTo(Imovel::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recebidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
