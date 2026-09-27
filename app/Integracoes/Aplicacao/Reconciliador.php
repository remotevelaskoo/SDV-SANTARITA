<?php

namespace App\Integracoes\Aplicacao;

use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\EstadoOutbox;
use App\Integracoes\Dominio\Enums\EstadoSincronizacao;
use App\Integracoes\Dominio\Enums\OperacaoIntegracao;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Models\CredencialSincronizacao;
use App\Models\Equipamento;
use App\Models\OperacaoIntegracao as Operacao;
use Illuminate\Support\Facades\DB;

/**
 * Reconciliação após resultado desconhecido ou indisponibilidade
 * (ADR-004 §36, ADR-007 §9, ADR-008 §10). Nunca reenvia um comando: só
 * consulta o terminal quando ele oferece essa prova; sem prova, a
 * pendência vai para intervenção humana.
 */
class Reconciliador
{
    public function __construct(private OutboxIntegracao $outbox) {}

    /** @return array{consultas: int, intervencoes: int} */
    public function reconciliar(Equipamento $equipamento): array
    {
        $consultas = $intervencoes = 0;

        if ($equipamento->status === StatusEquipamento::Inativo) {
            return compact('consultas', 'intervencoes');
        }

        $podeConsultarSincronizacao = $equipamento->suporta(Capacidade::ConsultarSincronizacao);
        $pendentes = CredencialSincronizacao::query()
            ->where('equipamento_id', $equipamento->id)
            ->where('estado', EstadoSincronizacao::AtualizacaoPendente->value)
            ->get();

        foreach ($pendentes as $sincronizacao) {
            $ultima = $sincronizacao->ultima_operacao_id ? Operacao::query()->find($sincronizacao->ultima_operacao_id) : null;
            if ($ultima === null || in_array($ultima->estado, [EstadoOutbox::Pendente, EstadoOutbox::Processando, EstadoOutbox::FalhaTemporaria], true)) {
                continue; // ainda há operação em curso
            }

            if (! $podeConsultarSincronizacao) {
                $sincronizacao->forceFill([
                    'estado' => EstadoSincronizacao::IntervencaoNecessaria,
                    'erro_sanitizado' => 'Resultado desconhecido e o terminal não permite consultar o estado.',
                ])->save();
                $intervencoes++;

                continue;
            }

            $esperado = $ultima->operacao === OperacaoIntegracao::RevogarCredencial ? 'ausente' : 'presente';
            if ($ultima->operacao === OperacaoIntegracao::ConsultarSincronizacao) {
                $esperado = $ultima->payload['esperado'] ?? 'presente';
            }

            $this->outbox->registrar(
                $equipamento,
                OperacaoIntegracao::ConsultarSincronizacao,
                ['credencial_id' => $sincronizacao->credencial_id, 'esperado' => $esperado],
                "reconciliar:{$sincronizacao->credencial_id}:{$ultima->id}",
                agregadoTipo: 'credencial',
                agregadoId: $sincronizacao->credencial_id,
                causationId: $ultima->id,
                correlationId: $ultima->correlation_id,
            );
            $consultas++;
        }

        $podeConsultarComando = $equipamento->suporta(Capacidade::ConsultarResultadoComando);
        $comandos = Operacao::query()
            ->where('equipamento_id', $equipamento->id)
            ->where('operacao', OperacaoIntegracao::AberturaRemota->value)
            ->where('resultado', ResultadoEquipamento::ConfirmacaoDesconhecida->value)
            ->where('estado', EstadoOutbox::Processado->value)
            ->get();

        foreach ($comandos as $comando) {
            if (! $podeConsultarComando) {
                DB::transaction(fn () => $comando->forceFill(['estado' => EstadoOutbox::IntervencaoNecessaria])->save());
                $intervencoes++;

                continue;
            }

            $this->outbox->registrar(
                $equipamento,
                OperacaoIntegracao::ConsultarResultadoComando,
                ['comando_id' => $comando->id],
                "reconciliar-comando:{$comando->id}",
                agregadoTipo: 'operacao_integracao',
                agregadoId: $comando->id,
                causationId: $comando->id,
                correlationId: $comando->correlation_id,
            );
            $consultas++;
        }

        return compact('consultas', 'intervencoes');
    }
}
