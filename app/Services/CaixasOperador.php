<?php

namespace App\Services;

use App\Integracoes\Aplicacao\TurnosOperacionais;
use App\Models\CaixaTurno;
use App\Models\Contribuicao;
use App\Models\User;
use App\Support\NormalizadorBusca;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Caixa próprio de cada operador e fechamento por turno (AJ-008,
 * SDV-UXR-014 §§5.4–5.6). A fórmula do saldo esperado está em CaixaTurno.
 */
class CaixasOperador
{
    public const PERMISSAO_PROPRIO = 'caixa.proprio.gerenciar';

    public const PERMISSAO_CONSOLIDADO = 'caixa.consolidado.consultar';

    public const TERMINAL_PADRAO = 'Caixa 01 — Guarita';

    public function __construct(private AuditService $audit, private TurnosOperacionais $turnos) {}

    /** Caixa aberto do operador autenticado na implantação ativa; nunca o "último caixa aberto". */
    public function abertoDo(User $operador, bool $bloquear = false): ?CaixaTurno
    {
        $consulta = CaixaTurno::query()->abertoDo($operador);

        return ($bloquear ? $consulta->lockForUpdate() : $consulta)->first();
    }

    public function abrir(User $operador, float $saldoInicial): CaixaTurno
    {
        if ($saldoInicial < 0) {
            throw ValidationException::withMessages(['openingBalanceInput' => 'O saldo inicial não pode ser negativo.']);
        }

        $posto = $this->turnos->turnoAtivo($operador)?->posto;

        try {
            return DB::transaction(function () use ($operador, $saldoInicial, $posto): CaixaTurno {
                if ($this->abertoDo($operador, bloquear: true) !== null) {
                    throw ValidationException::withMessages(['openingBalanceInput' => 'Você já tem um caixa aberto. Feche-o antes de abrir outro.']);
                }
                if ($posto !== null && CaixaTurno::query()->where('status', 'aberto')->where('posto_id', $posto->id)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['openingBalanceInput' => "O posto {$posto->nome} já tem um caixa aberto por outro operador. Ele precisa fechá-lo antes da troca."]);
                }

                $turno = CaixaTurno::query()->create([
                    'terminal' => $posto?->nome ?? self::TERMINAL_PADRAO,
                    'posto_id' => $posto?->id,
                    'operator_id' => $operador->id,
                    'opened_at' => now(),
                    'opening_balance' => round($saldoInicial, 2),
                    'status' => 'aberto',
                ]);

                $this->audit->record(
                    action: 'caixa_aberto',
                    module: 'caixa',
                    entityType: 'caixa_turnos',
                    entityId: $turno->id,
                    changes: ['opening_balance' => ['old' => null, 'new' => $turno->opening_balance]],
                    metadata: ['posto_id' => $posto?->id, 'terminal' => $turno->terminal],
                );

                return $turno;
            });
        } catch (UniqueConstraintViolationException) {
            // Duas aberturas simultâneas: o índice parcial do banco é a última defesa.
            throw ValidationException::withMessages(['openingBalanceInput' => 'Outro caixa foi aberto ao mesmo tempo para você ou para este posto. Recarregue a página.']);
        }
    }

    /**
     * Encerra o caixa do próprio operador. Diferença exige justificativa; os
     * totais conciliados ficam gravados e não mudam mais.
     */
    public function fechar(User $operador, float $contado, string $justificativa): CaixaTurno
    {
        $justificativa = trim($justificativa);

        return DB::transaction(function () use ($operador, $contado, $justificativa): CaixaTurno {
            // Bloqueia o caixa: nenhuma contribuição entra enquanto ele é conciliado.
            $turno = $this->abertoDo($operador, bloquear: true)
                ?? throw ValidationException::withMessages(['informedAmount' => 'Você não tem caixa aberto.']);

            $contribuicoes = $turno->contribuicoesTotal();
            $outras = $turno->outrasEntradasTotal();
            $saidas = $turno->saidasTotal();
            $estornos = $turno->estornosTotal();
            $esperado = round((float) $turno->opening_balance + $contribuicoes + $outras - $saidas - $estornos, 2);
            $diferenca = round($contado - $esperado, 2);
            $comDiferenca = abs($diferenca) >= 0.01;

            if ($comDiferenca && mb_strlen($justificativa) < 10) {
                throw ValidationException::withMessages(['closingNotes' => 'Há diferença de R$ '.number_format(abs($diferenca), 2, ',', '.').'. Justifique com pelo menos 10 caracteres para fechar.']);
            }
            if (mb_strlen($justificativa) > 500) {
                throw ValidationException::withMessages(['closingNotes' => 'Use até 500 caracteres.']);
            }

            $quantidades = $turno->quantidades();
            $turno->forceFill([
                'contribuicoes_total' => $contribuicoes,
                'outras_entradas_total' => $outras,
                'saidas_total' => $saidas,
                'estornos_total' => $estornos,
                'pagantes_quantidade' => $quantidades['pagantes'],
                'isentos_quantidade' => $quantidades['isentos'],
                'nao_contribuintes_quantidade' => $quantidades['nao_contribuintes'],
                'expected_amount' => $esperado,
                'informed_amount' => round($contado, 2),
                'difference' => $diferenca,
                'closing_notes' => $justificativa !== '' ? $justificativa : null,
                'closing_status' => $comDiferenca ? 'diferenca' : 'conferido',
                'numero_fechamento' => 'FCX-'.now()->format('Ymd').'-'.Str::upper(Str::random(5)),
                'closed_at' => now(),
                'closed_by' => $operador->id,
                'status' => 'fechado',
            ])->save();

            $this->audit->record(
                action: 'caixa_fechado',
                module: 'caixa',
                entityType: 'caixa_turnos',
                entityId: $turno->id,
                changes: [
                    'expected_amount' => ['old' => null, 'new' => $esperado],
                    'informed_amount' => ['old' => null, 'new' => $turno->informed_amount],
                    'difference' => ['old' => null, 'new' => $diferenca],
                ],
                justification: $turno->closing_notes,
                metadata: [
                    'numero_fechamento' => $turno->numero_fechamento,
                    'contribuicoes_total' => $contribuicoes,
                    'outras_entradas_total' => $outras,
                    'saidas_total' => $saidas,
                    'estornos_total' => $estornos,
                    ...$quantidades,
                ],
            );

            if ($comDiferenca) {
                $this->audit->record(
                    action: 'caixa_diferenca_registrada',
                    module: 'caixa',
                    entityType: 'caixa_turnos',
                    entityId: $turno->id,
                    changes: ['difference' => ['old' => null, 'new' => $diferenca]],
                    result: 'alerta',
                    justification: $turno->closing_notes,
                    metadata: ['numero_fechamento' => $turno->numero_fechamento],
                );
            }

            return $turno;
        });
    }

    public function podeConsultar(User $usuario, CaixaTurno $turno): bool
    {
        return $usuario->hasPermission(self::PERMISSAO_CONSOLIDADO)
            || ($usuario->hasPermission(self::PERMISSAO_PROPRIO) && $turno->operator_id === $usuario->id);
    }

    /** Consulta autorizada; ver o fechamento de outro operador é consulta consolidada e é auditada. */
    public function consultar(User $usuario, string $turnoId, string $acao = 'consultou_fechamento_caixa'): CaixaTurno
    {
        $turno = CaixaTurno::query()->with(['operator', 'posto', 'closedBy'])->findOrFail($turnoId);
        abort_unless($this->podeConsultar($usuario, $turno), 403);

        if ($turno->operator_id !== $usuario->id || $acao !== 'consultou_fechamento_caixa') {
            $this->audit->record(
                action: $acao,
                module: 'caixa',
                entityType: 'caixa_turnos',
                entityId: $turno->id,
                metadata: [
                    'numero_fechamento' => $turno->numero_fechamento,
                    'escopo' => $turno->operator_id === $usuario->id ? 'proprio' : 'consolidado',
                ],
            );
        }

        return $turno;
    }

    /**
     * Uma linha por contribuição paga vinculada ao turno, com documento mascarado.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function pagantes(CaixaTurno $turno): Collection
    {
        return $turno->contribuicoesPagas()
            ->with(['historicoAcesso', 'pessoa.documentos', 'operator', 'correcoesTicket'])
            ->orderBy('registrada_em')
            ->get()
            ->map(function (Contribuicao $contribuicao): array {
                $documento = $contribuicao->pessoa?->documentos->first();
                $correcoes = $contribuicao->correcoesTicket;

                return [
                    'id' => $contribuicao->id,
                    'horario' => $contribuicao->historicoAcesso?->occurred_at ?? $contribuicao->registrada_em,
                    'pessoa' => $contribuicao->pessoa?->nomeExibicao() ?? 'Pessoa não identificada',
                    'documento' => NormalizadorBusca::mascararDocumento($documento?->valor_apresentacao ?? $documento?->valor_normalizado),
                    'protocolo' => $contribuicao->historicoAcesso?->protocol,
                    'ticket' => $contribuicao->ticket_numero,
                    'valor' => (float) $contribuicao->valor,
                    'forma' => Contribuicao::rotuloForma($contribuicao->forma_pagamento),
                    'operador' => $contribuicao->operator?->name ?? 'Sistema',
                    'ponto' => $contribuicao->historicoAcesso?->ponto_acesso,
                    'ticket_repetido' => $contribuicao->ticket_duplicado,
                    'ticket_anterior' => $correcoes->first()?->valor_anterior,
                    'ticket_corrigido_em' => $correcoes->last()?->corrigido_em,
                ];
            });
    }
}
