<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

// implantacao_id é fillable pelo mesmo motivo documentado em PreRegistration.
#[Fillable(['implantacao_id', 'perfil_id', 'permissao_id'])]
class PerfilPermissao extends Pivot
{
    use BelongsToImplantacao, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'perfil_permissoes';

    /** @return BelongsTo<Perfil, $this> */
    public function perfil(): BelongsTo
    {
        return $this->belongsTo(Perfil::class);
    }

    /** @return BelongsTo<Permissao, $this> */
    public function permissao(): BelongsTo
    {
        return $this->belongsTo(Permissao::class);
    }
}
