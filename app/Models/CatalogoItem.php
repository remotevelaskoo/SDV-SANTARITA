<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['implantacao_id', 'catalogo_id', 'codigo', 'rotulo', 'status', 'ordem'])]
class CatalogoItem extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'catalogo_itens';

    /** @return BelongsTo<Catalogo, $this> */
    public function catalogo(): BelongsTo
    {
        return $this->belongsTo(Catalogo::class);
    }
}
