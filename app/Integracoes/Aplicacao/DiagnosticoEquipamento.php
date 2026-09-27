<?php

namespace App\Integracoes\Aplicacao;

use App\Integracoes\Dominio\Enums\OperacaoIntegracao;
use App\Models\Equipamento;
use App\Models\OperacaoIntegracao as Operacao;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Operações de diagnóstico e coleta: testar conexão, consultar capacidades
 * e coletar eventos. Todas passam pela outbox; nenhuma simula sucesso
 * (docs/008 §23.5, CA-ADM-020).
 */
class DiagnosticoEquipamento
{
    public function __construct(private OutboxIntegracao $outbox, private AuditService $audit) {}

    public function testarConexao(Equipamento $equipamento, ?User $ator = null, ?string $chave = null): Operacao
    {
        return $this->solicitar($equipamento, OperacaoIntegracao::TestarConexao, [], $chave, $ator);
    }

    public function consultarCapacidades(Equipamento $equipamento, ?User $ator = null, ?string $chave = null): Operacao
    {
        return $this->solicitar($equipamento, OperacaoIntegracao::ConsultarCapacidades, [], $chave, $ator);
    }

    public function coletarEventos(Equipamento $equipamento, ?string $cursor = null, ?User $ator = null, ?string $chave = null): Operacao
    {
        return $this->solicitar($equipamento, OperacaoIntegracao::ColetarEventos, ['cursor' => $cursor], $chave, $ator);
    }

    /** @param  array<string, scalar|null>  $payload */
    private function solicitar(Equipamento $equipamento, OperacaoIntegracao $tipo, array $payload, ?string $chave, ?User $ator): Operacao
    {
        return DB::transaction(function () use ($equipamento, $tipo, $payload, $chave, $ator): Operacao {
            $operacao = $this->outbox->registrar(
                $equipamento,
                $tipo,
                $payload,
                $chave ?? $tipo->value.':'.Str::uuid7(),
                agregadoTipo: 'equipamento',
                agregadoId: $equipamento->id,
                ator: $ator,
                origem: $ator ? 'manual' : 'sistema',
            );

            if ($operacao->wasRecentlyCreated) {
                $this->audit->record(
                    action: $tipo->value.'_solicitado',
                    module: 'integracoes',
                    entityType: 'equipamentos',
                    entityId: $equipamento->id,
                    metadata: ['operacao_id' => $operacao->id],
                );
            }

            return $operacao;
        });
    }
}
