<?php

namespace App\Models;

use App\Integracoes\Dominio\Enums\EstadoSincronizacao;
use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'implantacao_id', 'equipamento_id', 'credencial_id', 'estado', 'ultima_operacao_id',
    'ultima_tentativa_em', 'sincronizado_em', 'removido_em', 'erro_sanitizado', 'versao',
])]
class CredencialSincronizacao extends Model
{
    use BelongsToImplantacao, HasUuids;

    protected $table = 'credencial_sincronizacoes';

    protected function casts(): array
    {
        return [
            'estado' => EstadoSincronizacao::class,
            'ultima_tentativa_em' => 'datetime',
            'sincronizado_em' => 'datetime',
            'removido_em' => 'datetime',
            'versao' => 'integer',
        ];
    }

    /** @return BelongsTo<Equipamento, $this> */
    public function equipamento(): BelongsTo
    {
        return $this->belongsTo(Equipamento::class);
    }
}
