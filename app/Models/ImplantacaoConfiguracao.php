<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['implantacao_id', 'configuracao_id', 'valor', 'updated_by'])]
class ImplantacaoConfiguracao extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'implantacao_configuracoes';

    /** @return BelongsTo<Configuracao, $this> */
    public function configuracao(): BelongsTo
    {
        return $this->belongsTo(Configuracao::class);
    }
}
