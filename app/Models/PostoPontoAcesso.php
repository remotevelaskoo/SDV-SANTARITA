<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ponto de acesso atendido por um posto, com histórico (ADR-017). */
#[Fillable(['implantacao_id', 'posto_id', 'ponto_acesso_id', 'started_at', 'ended_at', 'created_by'])]
class PostoPontoAcesso extends Model
{
    use BelongsToImplantacao, HasUuids;

    protected $table = 'posto_pontos_acesso';

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    /** @return BelongsTo<Posto, $this> */
    public function posto(): BelongsTo
    {
        return $this->belongsTo(Posto::class);
    }

    /** @return BelongsTo<PontoAcesso, $this> */
    public function pontoAcesso(): BelongsTo
    {
        return $this->belongsTo(PontoAcesso::class);
    }
}
