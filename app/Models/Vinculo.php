<?php

namespace App\Models;

use App\Exceptions\InvalidTemporalRangeException;
use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// implantacao_id é fillable pelo mesmo motivo documentado em PreRegistration.
#[Fillable([
    'implantacao_id', 'pessoa_id', 'imovel_id', 'tipo', 'papel',
    'status', 'origem', 'started_at', 'ended_at', 'versao', 'created_by',
])]
class Vinculo extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'vinculos';

    /**
     * docs/010 (seção 29.1) pede integridade temporal preferencialmente no
     * banco; sem suporte portátil a CHECK constraint no schema builder desta
     * versão do Laravel, a validação fica na aplicação. O mesmo princípio
     * vale para enderecos_imoveis/pessoa_documentos/pessoa_contatos/
     * pessoa_enderecos, ainda sem essa checagem — pendência a retomar.
     */
    protected static function booted(): void
    {
        static::saving(function (self $vinculo): void {
            if ($vinculo->ended_at !== null && $vinculo->ended_at <= $vinculo->started_at) {
                throw new InvalidTemporalRangeException;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'versao' => 'integer',
        ];
    }

    /** @return BelongsTo<Pessoa, $this> */
    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    /** @return BelongsTo<Imovel, $this> */
    public function imovel(): BelongsTo
    {
        return $this->belongsTo(Imovel::class);
    }

    /** @return HasMany<ImovelResponsabilidade, $this> */
    public function responsabilidades(): HasMany
    {
        return $this->hasMany(ImovelResponsabilidade::class);
    }

    public function ativo(): bool
    {
        return $this->status === 'ativo' && $this->ended_at === null;
    }
}
