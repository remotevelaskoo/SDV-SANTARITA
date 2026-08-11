<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// implantacao_id é fillable pelo mesmo motivo documentado em PreRegistration.
// valor_normalizado guardado em texto simples nesta fatia — ver nota na migration
// (2026_08_11_150001_create_pessoa_documentos_table) sobre PEN-BDD-007.
#[Fillable([
    'implantacao_id', 'pessoa_id', 'tipo', 'pais',
    'valor_normalizado', 'valor_apresentacao', 'status',
    'started_at', 'ended_at',
])]
class PessoaDocumento extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'pessoa_documentos';

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
