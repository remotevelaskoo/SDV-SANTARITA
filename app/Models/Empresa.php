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
    'implantacao_id', 'cnpj', 'razao_social', 'nome_fantasia',
    'categoria', 'status', 'telefone', 'email', 'versao',
])]
class Empresa extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'empresas';

    protected function casts(): array
    {
        return [
            'versao' => 'integer',
        ];
    }

    /** @return HasMany<EmpresaPrestador, $this> */
    public function prestadores(): HasMany
    {
        return $this->hasMany(EmpresaPrestador::class);
    }

    /** @return HasMany<EmpresaDocumento, $this> */
    public function documentos(): HasMany
    {
        return $this->hasMany(EmpresaDocumento::class);
    }

    /** @return HasMany<EmpresaServico, $this> */
    public function servicos(): HasMany
    {
        return $this->hasMany(EmpresaServico::class);
    }
}
