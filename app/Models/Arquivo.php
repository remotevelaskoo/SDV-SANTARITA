<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

// implantacao_id é fillable pelo mesmo motivo documentado em PreRegistration.
#[Fillable([
    'implantacao_id', 'fileable_type', 'fileable_id', 'categoria',
    'disco', 'caminho', 'nome_original', 'mime', 'tamanho',
    'checksum', 'estado', 'created_by',
])]
class Arquivo extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'tamanho' => 'integer',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }

    public function urlProtegida(): string
    {
        return route('arquivos.mostrar', $this);
    }
}
