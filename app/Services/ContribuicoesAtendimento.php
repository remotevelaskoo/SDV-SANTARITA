<?php

namespace App\Services;

use App\Models\CaixaTurno;
use App\Models\Configuracao;
use App\Models\Contribuicao;
use App\Models\ContribuicaoTicketCorrecao;
use App\Models\HistoricoAcesso;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Contribuição e número do ticket de acesso (AJ-006, fatia 2, docs/020 §3).
 *
 * - "Contribui/Pago" exige ticket; "Não contribui" e "Isento" o deixam opcional;
 * - o ticket é texto livre de série: zeros à esquerda são preservados e não
 *   há validação fiscal inventada;
 * - ticket repetido gera alerta e só segue com confirmação explícita;
 * - correção posterior exige permissão e justificativa, guarda o valor
 *   anterior e é auditada.
 */
class ContribuicoesAtendimento
{
    public const PERMISSAO_CORRECAO = 'contribuicao.corrigir-ticket';

    public const TICKET_REGEX = '/^[0-9A-Za-z][0-9A-Za-z.\/-]{0,29}$/';

    /** Mapeia a escolha da tela para a classificação gravada. */
    public const DA_TELA = ['yes' => 'contribui', 'no' => 'nao_contribui', 'exempt' => 'isento'];

    public function __construct(private AuditService $audit) {}

    public static function valorPadrao(): string
    {
        $valor = Configuracao::obter('contribuicao.valor_padrao');

        return is_numeric($valor) ? number_format((float) $valor, 2, '.', '') : '15.00';
    }

    public static function normalizarTicket(?string $ticket): ?string
    {
        $ticket = trim((string) $ticket);

        return $ticket === '' ? null : $ticket;
    }

    public function registrar(HistoricoAcesso $historico, string $classificacao, ?string $forma, ?string $ticket, bool $duplicadoConfirmado, ?User $operador): Contribuicao
    {
        $ticket = self::normalizarTicket($ticket);
        $this->validarTicket($classificacao, $ticket);

        $duplicado = $ticket !== null && Contribuicao::ticketJaUsado($ticket) !== null;
        if ($duplicado && ! $duplicadoConfirmado) {
            throw ValidationException::withMessages(['ticketNumero' => 'Este ticket já foi registrado em outro atendimento. Confira e confirme para continuar.']);
        }

        $pago = $classificacao === 'contribui';

        return Contribuicao::query()->create([
            'historico_acesso_id' => $historico->id,
            'pessoa_id' => $historico->pessoa_id,
            'classificacao' => $classificacao,
            'situacao_pagamento' => match ($classificacao) {
                'contribui' => 'pago',
                'isento' => 'isento',
                default => 'sem_pagamento',
            },
            'valor' => $pago ? self::valorPadrao() : null,
            'forma_pagamento' => $pago ? $forma : null,
            'ticket_numero' => $ticket,
            'ticket_duplicado' => $duplicado,
            'caixa_turno_id' => $operador ? CaixaTurno::query()->where('operator_id', $operador->id)->where('status', 'aberto')->value('id') : null,
            'operator_id' => $operador?->id,
            'registrada_em' => now(),
            'versao' => 1,
        ]);
    }

    public function corrigirTicket(string $contribuicaoId, ?string $novoTicket, string $justificativa, User $ator): Contribuicao
    {
        abort_unless($ator->hasPermission(self::PERMISSAO_CORRECAO), 403);

        $justificativa = trim($justificativa);
        if (mb_strlen($justificativa) < 10 || mb_strlen($justificativa) > 300) {
            throw ValidationException::withMessages(['justificativaTicket' => 'Explique a correção com 10 a 300 caracteres.']);
        }

        return DB::transaction(function () use ($contribuicaoId, $novoTicket, $justificativa, $ator): Contribuicao {
            $contribuicao = Contribuicao::query()->lockForUpdate()->findOrFail($contribuicaoId);
            $novo = self::normalizarTicket($novoTicket);
            $this->validarTicket($contribuicao->classificacao, $novo, 'novoTicket');

            $anterior = $contribuicao->ticket_numero;
            if ($anterior === $novo) {
                throw ValidationException::withMessages(['novoTicket' => 'O número informado é igual ao atual.']);
            }

            ContribuicaoTicketCorrecao::query()->create([
                'contribuicao_id' => $contribuicao->id,
                'valor_anterior' => $anterior,
                'valor_novo' => $novo,
                'justificativa' => $justificativa,
                'operator_id' => $ator->id,
                'corrigido_em' => now(),
            ]);

            $contribuicao->forceFill([
                'ticket_numero' => $novo,
                'ticket_duplicado' => $novo !== null && Contribuicao::ticketJaUsado($novo, $contribuicao->id) !== null,
                'versao' => $contribuicao->versao + 1,
            ])->save();

            $this->audit->record(
                action: 'corrigiu_ticket_contribuicao',
                module: 'contribuicao',
                entityType: 'contribuicoes',
                entityId: $contribuicao->id,
                changes: ['ticket_numero' => ['old' => $anterior, 'new' => $novo]],
                justification: $justificativa,
                metadata: ['historico_acesso_id' => $contribuicao->historico_acesso_id, 'versao' => $contribuicao->versao],
            );

            return $contribuicao;
        });
    }

    private function validarTicket(string $classificacao, ?string $ticket, string $campo = 'ticketNumero'): void
    {
        if ($classificacao === 'contribui' && $ticket === null) {
            throw ValidationException::withMessages([$campo => 'Informe o número do ticket de acesso para contribuição paga.']);
        }
        if ($ticket !== null && ! preg_match(self::TICKET_REGEX, $ticket)) {
            throw ValidationException::withMessages([$campo => 'Use até 30 letras, números, ponto, barra ou hífen.']);
        }
    }
}
