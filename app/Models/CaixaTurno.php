<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Caixa de um operador num turno (P14, AJ-008).
 *
 * Fonte financeira canônica (SDV-UXR-014 §5.5):
 *
 *   esperado = saldo inicial
 *            + contribuições pagas vinculadas ao turno   (contribuicoes)
 *            + outras entradas manuais                   (caixa_movimentacoes "entrada")
 *            − saídas                                    (caixa_movimentacoes "saida")
 *            − estornos                                  (caixa_movimentacoes "estorno")
 *
 * Contribuições pagas nunca são projetadas em `caixa_movimentacoes`; uma
 * entrada manual com o protocolo de uma contribuição paga do mesmo turno é
 * desconsiderada, para que nenhum pagamento seja somado duas vezes.
 *
 * Fechado, o caixa não é editado nem excluído: os totais ficam gravados no
 * próprio registro como retrato do encerramento.
 */
// implantacao_id é fillable pelo mesmo motivo documentado em PreRegistration.
#[Fillable([
    'implantacao_id', 'terminal', 'posto_id', 'operator_id', 'opened_at', 'opening_balance',
    'closed_at', 'informed_amount', 'expected_amount', 'difference',
    'closing_notes', 'status', 'closing_status',
])]
class CaixaTurno extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'caixa_turnos';

    protected static function booted(): void
    {
        static::updating(function (self $turno): void {
            if ($turno->getOriginal('status') === 'fechado') {
                throw new LogicException('Caixa fechado não pode ser alterado (RN-048).');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Caixa não é excluído; o histórico é preservado (RN-048).');
        });
    }

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_balance' => 'decimal:2',
            'informed_amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'difference' => 'decimal:2',
            'contribuicoes_total' => 'decimal:2',
            'outras_entradas_total' => 'decimal:2',
            'saidas_total' => 'decimal:2',
            'estornos_total' => 'decimal:2',
            'pagantes_quantidade' => 'integer',
            'isentos_quantidade' => 'integer',
            'nao_contribuintes_quantidade' => 'integer',
        ];
    }

    /** @param  Builder<CaixaTurno>  $query */
    public function scopeAbertoDo(Builder $query, User|int $operador): void
    {
        $query->where('status', 'aberto')->where('operator_id', $operador instanceof User ? $operador->id : $operador);
    }

    /** @return BelongsTo<User, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return BelongsTo<Posto, $this> */
    public function posto(): BelongsTo
    {
        return $this->belongsTo(Posto::class);
    }

    /** @return HasMany<CaixaMovimentacao, $this> */
    public function movimentacoes(): HasMany
    {
        return $this->hasMany(CaixaMovimentacao::class);
    }

    /** @return HasMany<Contribuicao, $this> */
    public function contribuicoes(): HasMany
    {
        return $this->hasMany(Contribuicao::class);
    }

    /** @return HasMany<Contribuicao, $this> */
    public function contribuicoesPagas(): HasMany
    {
        return $this->contribuicoes()->where('situacao_pagamento', 'pago');
    }

    public function fechado(): bool
    {
        return $this->status === 'fechado';
    }

    public function contribuicoesTotal(): float
    {
        return $this->fechado() && $this->contribuicoes_total !== null
            ? (float) $this->contribuicoes_total
            : round((float) $this->contribuicoesPagas()->sum('valor'), 2);
    }

    /** Entradas manuais, sem as que repetem uma contribuição paga deste turno. */
    public function outrasEntradasTotal(): float
    {
        if ($this->fechado() && $this->outras_entradas_total !== null) {
            return (float) $this->outras_entradas_total;
        }

        $protocolosPagos = HistoricoAcesso::query()
            ->whereIn('id', $this->contribuicoesPagas()->select('historico_acesso_id'))
            ->select('protocol');

        return round((float) $this->movimentacoes()
            ->where('type', 'entrada')
            ->where(fn (Builder $q) => $q->whereNull('protocol')->orWhereNotIn('protocol', $protocolosPagos))
            ->sum('amount'), 2);
    }

    public function saidasTotal(): float
    {
        return $this->fechado() && $this->saidas_total !== null
            ? (float) $this->saidas_total
            : round((float) $this->movimentacoes()->where('type', 'saida')->sum('amount'), 2);
    }

    public function estornosTotal(): float
    {
        return $this->fechado() && $this->estornos_total !== null
            ? (float) $this->estornos_total
            : round((float) $this->movimentacoes()->where('type', 'estorno')->sum('amount'), 2);
    }

    /** Contribuições pagas + outras entradas. */
    public function incomeTotal(): float
    {
        return round($this->contribuicoesTotal() + $this->outrasEntradasTotal(), 2);
    }

    /** Saídas + estornos. */
    public function outflowTotal(): float
    {
        return round($this->saidasTotal() + $this->estornosTotal(), 2);
    }

    public function cancellationsTotal(): float
    {
        return $this->estornosTotal();
    }

    public function expectedBalance(): float
    {
        return round((float) $this->opening_balance + $this->incomeTotal() - $this->outflowTotal(), 2);
    }

    /** @return array{pagantes: int, isentos: int, nao_contribuintes: int} */
    public function quantidades(): array
    {
        if ($this->fechado() && $this->pagantes_quantidade !== null) {
            return [
                'pagantes' => (int) $this->pagantes_quantidade,
                'isentos' => (int) $this->isentos_quantidade,
                'nao_contribuintes' => (int) $this->nao_contribuintes_quantidade,
            ];
        }

        $contagem = $this->contribuicoes()->selectRaw('situacao_pagamento, count(*) as total')->groupBy('situacao_pagamento')->pluck('total', 'situacao_pagamento');

        return [
            'pagantes' => (int) ($contagem['pago'] ?? 0),
            'isentos' => (int) ($contagem['isento'] ?? 0),
            'nao_contribuintes' => (int) ($contagem['sem_pagamento'] ?? 0),
        ];
    }
}
