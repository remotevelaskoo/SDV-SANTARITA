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
