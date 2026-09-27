<?php

namespace App\Integracoes\Aplicacao;

use App\Integracoes\Dominio\Dados\EventoEquipamento;
use App\Integracoes\Infra\SanitizadorIntegracao;
use App\Models\Equipamento;
use App\Models\EquipamentoEventoRecebido;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Inbox dos eventos do terminal (ADR-005 §19, ADR-007 §13): deduplica pelo
 * identificador externo, preserva o instante do equipamento separado do
 * recebimento e registra divergência de relógio (ADR-016 §12).
 */
class ColetorEventos
{
    public function __construct(private SanitizadorIntegracao $sanitizador) {}

    /**
     * @param  list<EventoEquipamento>  $eventos
     * @return array{0: int, 1: int} [novos, duplicados]
     */
    public function registrar(Equipamento $equipamento, array $eventos, ?string $correlationId = null): array
    {
        $novos = $duplicados = 0;
        $tolerancia = (int) config('integracoes.tolerancia_relogio_segundos');

        foreach ($eventos as $evento) {
            $dados = $this->sanitizador->dados($evento->dados);
            $hash = hash('sha256', json_encode([
                $evento->idExterno, $evento->tipo, $evento->ocorridoEm?->format(DATE_ATOM),
                $evento->direcao, $evento->resultado, $evento->referenciaCredencialExterna, $dados,
            ], JSON_THROW_ON_ERROR));

            if (EquipamentoEventoRecebido::query()->where('equipamento_id', $equipamento->id)->where('id_externo_evento', $evento->idExterno)->exists()) {
                $duplicados++;

                continue;
            }

            $recebidoEm = now();
            $divergencia = $evento->ocorridoEm === null ? null : $recebidoEm->getTimestamp() - $evento->ocorridoEm->getTimestamp();

            try {
                DB::transaction(fn () => EquipamentoEventoRecebido::query()->create([
                    'equipamento_id' => $equipamento->id,
                    'id_externo_evento' => $evento->idExterno,
                    'hash_payload' => $hash,
                    'tipo' => mb_substr($evento->tipo, 0, 60),
                    'direcao' => $evento->direcao,
                    'resultado_equipamento' => $evento->resultado,
                    'referencia_credencial_externa' => $evento->referenciaCredencialExterna,
                    'ocorrido_no_equipamento_em' => $evento->ocorridoEm,
                    'recebido_em' => $recebidoEm,
                    'divergencia_relogio_segundos' => $divergencia !== null && abs($divergencia) > $tolerancia ? $divergencia : null,
                    'dados_sanitizados' => $dados ?: null,
                    'correlation_id' => $correlationId,
                ]));
                $novos++;
            } catch (UniqueConstraintViolationException) {
                $duplicados++;
            }
        }

        return [$novos, $duplicados];
    }
}
