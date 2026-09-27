<?php

namespace App\Integracoes\Aplicacao;

use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\OperacaoIntegracao;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Integracoes\Dominio\Enums\StatusPontoAcesso;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\OperacaoIntegracao as Operacao;
use App\Models\PontoAcesso;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Abertura remota de um ponto (ADR-007 §7-10, RN-078 a RN-080).
 *
 * Só é aceita quando: a abertura remota está habilitada na instalação, há
 * uma decisão de acesso do SDV referenciada, o ponto está ativo, o terminal
 * vigente está ativo e comprovou a capacidade, e há um operador
 * identificado. A chave idempotente impede comando duplicado; o comando
 * expira se não for enviado a tempo e nunca é reenviado às cegas.
 */
class ComandosAbertura
{
    public function __construct(private OutboxIntegracao $outbox, private AuditService $audit) {}

    public function solicitar(PontoAcesso $ponto, string $decisaoReferencia, string $chaveIdempotencia, User $ator, string $motivo, string $origem = 'manual'): Operacao
    {
        if (! config('integracoes.abertura_remota_habilitada')) {
            throw new RegraIntegracaoViolada('abertura_remota_desabilitada', 'Abertura remota desabilitada até a homologação em bancada do contato seco (ADR-016).');
        }
        if (! Str::isUuid($decisaoReferencia)) {
            throw new RegraIntegracaoViolada('sem_decisao', 'A abertura exige a referência da decisão de acesso registrada no SDV.');
        }
        if (trim($motivo) === '') {
            throw new RegraIntegracaoViolada('sem_motivo', 'Informe o motivo da abertura remota.');
        }
        if ($ponto->status !== StatusPontoAcesso::Ativo) {
            throw new RegraIntegracaoViolada('ponto_nao_ativo', 'O ponto de acesso não está ativo.');
        }

        $equipamento = $ponto->vinculoVigente?->equipamento
            ?? throw new RegraIntegracaoViolada('sem_equipamento', 'O ponto de acesso não possui terminal vigente.');

        if ($equipamento->status !== StatusEquipamento::Ativo) {
            throw new RegraIntegracaoViolada('equipamento_nao_ativo', 'O terminal do ponto não está ativo.');
        }
        if (! $equipamento->suporta(Capacidade::AberturaRemota)) {
            throw new RegraIntegracaoViolada('capacidade_ausente', 'O terminal não comprovou suporte a abertura remota.');
        }

        return DB::transaction(function () use ($ponto, $equipamento, $decisaoReferencia, $chaveIdempotencia, $ator, $motivo, $origem): Operacao {
            $operacao = $this->outbox->registrar(
                $equipamento,
                OperacaoIntegracao::AberturaRemota,
                [
                    'ponto_acesso_id' => $ponto->id,
                    'decisao_referencia' => $decisaoReferencia,
                ],
                $chaveIdempotencia,
                agregadoTipo: 'decisao_acesso',
                agregadoId: $decisaoReferencia,
                ator: $ator,
                expiraEm: now()->addSeconds((int) config('integracoes.abertura_validade_segundos')),
                origem: $origem,
            );

            if ($operacao->wasRecentlyCreated) {
                $this->audit->record(
                    action: 'abertura_remota_solicitada',
                    module: 'integracoes',
                    entityType: 'pontos_acesso',
                    entityId: $ponto->id,
                    justification: $motivo,
                    metadata: ['operacao_id' => $operacao->id, 'decisao_referencia' => $decisaoReferencia, 'equipamento_id' => $equipamento->id],
                );
            }

            return $operacao;
        });
    }
}
