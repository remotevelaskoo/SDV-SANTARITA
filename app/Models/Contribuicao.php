<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Contribuição do atendimento (AJ-006). Registra a classificação e o
 * pagamento; não autoriza entrada (docs/010 §15.3).
 */
#[Fillable([
    'implantacao_id', 'historico_acesso_id', 'pessoa_id', 'classificacao', 'situacao_pagamento',
    'valor', 'forma_pagamento', 'ticket_numero', 'ticket_duplicado', 'caixa_turno_id',
    'operator_id', 'registrada_em', 'versao',
])]
class Contribuicao extends Model
{
    use BelongsToImplantacao, HasUuids;

    public const CLASSIFICACOES = ['contribui', 'nao_contribui', 'isento'];

    protected $table = 'contribuicoes';

    /** Campos que entram no fechamento do caixa: gravados uma vez, nunca reescritos (AJ-008). */
    public const CAMPOS_FINANCEIROS = ['classificacao', 'situacao_pagamento', 'valor', 'forma_pagamento', 'caixa_turno_id', 'historico_acesso_id'];

    protected static function booted(): void
    {
        static::updating(function (self $contribuicao): void {
            if (array_intersect(array_keys($contribuicao->getDirty()), self::CAMPOS_FINANCEIROS) !== []) {
                throw new \LogicException('Dados financeiros da contribuição não são alterados depois do registro.');
            }
        });

        static::deleting(fn () => throw new \LogicException('Contribuição não é excluída; o histórico é preservado.'));
    }

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'ticket_duplicado' => 'boolean',
            'registrada_em' => 'datetime',
            'versao' => 'integer',
        ];
    }

    /** @return BelongsTo<HistoricoAcesso, $this> */
    public function historicoAcesso(): BelongsTo
    {
        return $this->belongsTo(HistoricoAcesso::class);
    }

    /** @return BelongsTo<CaixaTurno, $this> */
    public function caixaTurno(): BelongsTo
    {
        return $this->belongsTo(CaixaTurno::class);
    }

    /** @return BelongsTo<Pessoa, $this> */
    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    /** @return BelongsTo<User, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /** @return HasMany<ContribuicaoTicketCorrecao, $this> */
    public function correcoesTicket(): HasMany
    {
        return $this->hasMany(ContribuicaoTicketCorrecao::class)->orderBy('corrigido_em');
    }

    public function situacaoRotulo(): string
    {
        return self::rotuloSituacao($this->situacao_pagamento);
    }

    public static function rotuloSituacao(?string $situacao): string
    {
        return match ($situacao) {
            'pago' => 'Contribui/Pago',
            'sem_pagamento' => 'Não contribui',
            'isento' => 'Isento',
            default => 'Sem registro',
        };
    }

    public static function rotuloForma(?string $forma): string
    {
        return match ($forma) {
            'dinheiro' => 'Dinheiro',
            'pix' => 'Pix',
            'cartao' => 'Cartão',
            null => '—',
            default => ucfirst($forma),
        };
    }

    /** Mesmo ticket já usado em outro atendimento da implantação (alerta, não bloqueio). */
    public static function ticketJaUsado(string $ticket, ?string $ignorarId = null): ?self
    {
        return static::query()
            ->where('ticket_numero', $ticket)
            ->when($ignorarId, fn ($q) => $q->whereKeyNot($ignorarId))
            ->with('historicoAcesso')
            ->latest('registrada_em')
            ->first();
    }
}
