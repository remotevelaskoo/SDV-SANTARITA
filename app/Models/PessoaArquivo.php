<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'implantacao_id', 'pessoa_id', 'arquivo_id', 'category',
    'is_current', 'linked_at', 'replaced_at',
])]
class PessoaArquivo extends Model
{
    use BelongsToImplantacao, HasUuids;

    protected $table = 'pessoa_arquivos';

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'linked_at' => 'datetime',
            'replaced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Pessoa, $this> */
    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    /** @return BelongsTo<Arquivo, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(Arquivo::class, 'arquivo_id');
    }
}
