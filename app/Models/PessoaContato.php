<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use App\Support\NormalizadorBusca;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// implantacao_id é fillable pelo mesmo motivo documentado em PreRegistration.
#[Fillable([
    'implantacao_id', 'pessoa_id', 'tipo', 'valor',
    'principal', 'verificado', 'started_at', 'ended_at',
])]
class PessoaContato extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'pessoa_contatos';

    protected static function booted(): void
    {
        // Coluna derivada para a busca por telefone na Validação de Entrada (AJ-006).
        static::saving(function (self $contato): void {
            $contato->valor_busca = $contato->tipo === 'email'
                ? mb_strtolower(trim((string) $contato->valor))
                : NormalizadorBusca::digitos($contato->valor);
        });
    }

    protected function casts(): array
    {
        return [
            'principal' => 'boolean',
            'verificado' => 'boolean',
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
