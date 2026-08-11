<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// implantacao_id é fillable pelo mesmo motivo documentado em PreRegistration.
//
// Como todo o resto do P18, esta associação é criada sempre no escopo da
// implantação "atual" (BelongsToImplantacao). Enquanto existir uma única
// implantação isso é inofensivo; quando existir troca real de contexto
// (P19/P20/P21), operações administrativas entre implantações vão
// precisar de um caminho explícito que não dependa de current().
#[Fillable(['implantacao_id', 'user_id', 'status'])]
class UsuarioImplantacao extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'usuario_implantacoes';

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
