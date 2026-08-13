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
    'implantacao_id', 'arquivo_id', 'ator_id', 'contexto', 'resultado', 'occurred_at',
])]
class ArquivoAcesso extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Arquivo, $this> */
    public function arquivo(): BelongsTo
    {
        return $this->belongsTo(Arquivo::class);
    }

    /** @return BelongsTo<User, $this> */
    public function ator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ator_id');
    }
}
