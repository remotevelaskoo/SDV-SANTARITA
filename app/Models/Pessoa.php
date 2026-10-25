<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use App\Support\NormalizadorBusca;
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

    protected static function booted(): void
    {
        // Coluna derivada para a busca da Validação de Entrada (AJ-006).
        static::saving(function (self $pessoa): void {
            $pessoa->nome_busca = NormalizadorBusca::texto(trim($pessoa->nome.' '.($pessoa->nome_social ?? '')));
        });
    }

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

    /** @return HasMany<Vinculo, $this> */
    public function vinculos(): HasMany
    {
        return $this->hasMany(Vinculo::class);
    }

    /** @return HasMany<VeiculoVinculo, $this> */
    public function veiculoVinculos(): HasMany
    {
        return $this->hasMany(VeiculoVinculo::class);
    }

    /** @return HasMany<PessoaArquivo, $this> */
    public function arquivos(): HasMany
    {
        return $this->hasMany(PessoaArquivo::class)->orderByDesc('linked_at');
    }

    public function fotoVigente(): ?PessoaArquivo
    {
        return $this->arquivos()
            ->where('category', 'foto')
            ->where('is_current', true)
            ->with('file')
            ->first();
    }

    public function nomeExibicao(): string
    {
        return $this->nome_social ?: $this->nome;
    }
}
