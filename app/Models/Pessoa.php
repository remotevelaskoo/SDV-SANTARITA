<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// implantacao_id é fillable pelo mesmo motivo documentado em PreRegistration.
#[Fillable([
    'implantacao_id', 'nome', 'nome_social', 'data_nascimento',
    'status', 'versao', 'created_by', 'updated_by',
])]
class Pessoa extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'pessoas';

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
            'versao' => 'integer',
        ];
    }

    /** @return HasMany<PessoaDocumento, $this> */
    public function documentos(): HasMany
    {
        return $this->hasMany(PessoaDocumento::class);
    }

    /** @return HasMany<PessoaContato, $this> */
    public function contatos(): HasMany
    {
        return $this->hasMany(PessoaContato::class);
    }

    /** @return HasMany<PessoaEndereco, $this> */
    public function enderecos(): HasMany
    {
        return $this->hasMany(PessoaEndereco::class);
    }

    public function nomeExibicao(): string
    {
        return $this->nome_social ?: $this->nome;
    }
}
