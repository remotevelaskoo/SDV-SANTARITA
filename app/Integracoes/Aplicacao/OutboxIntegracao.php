<?php

namespace App\Integracoes\Aplicacao;

use App\Integracoes\Dominio\Enums\EstadoOutbox;
use App\Integracoes\Dominio\Enums\OperacaoIntegracao;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;
use App\Integracoes\Dominio\Excecoes\PayloadDivergente;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Jobs\ProcessarOperacaoIntegracao;
use App\Models\Equipamento;
use App\Models\OperacaoIntegracao as Operacao;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Grava operações de integração na outbox dentro da transação do caso de
 * uso e só publica o job depois do commit (ADR-004 §19, ADR-005 §12).
 *
 * A mesma chave idempotente com o mesmo conteúdo devolve a operação já
 * existente; com conteúdo diferente, falha (ADR-005 §18.3). Nenhum efeito
 * externo acontece aqui.
 */
class OutboxIntegracao
{
    private const CHAVES_PROIBIDAS = '/pass(word)?|senha|secret|segredo|token|authori[sz]ation|imagem|image|foto|photo|selfie|template|biometr/i';

    /** @param  array<string, scalar|null>  $payload */
    public function registrar(
        Equipamento $equipamento,
        OperacaoIntegracao $operacao,
        array $payload,
        string $chaveIdempotencia,
        ?string $agregadoTipo = null,
        ?string $agregadoId = null,
        ?User $ator = null,
        ?DateTimeInterface $expiraEm = null,
        ?string $correlationId = null,
        ?string $causationId = null,
        string $origem = 'sistema',
    ): Operacao {
        $this->garantirPayloadSeguro($payload);
        $hash = $this->hash($equipamento->id, $operacao, $payload);

        $existente = $this->buscar($operacao, $chaveIdempotencia);
        if ($existente !== null) {
            return $this->mesmaOperacaoOuFalha($existente, $hash, $chaveIdempotencia);
        }

        try {
            // Savepoint: no PostgreSQL uma violação de unicidade aborta a
            // transação externa se não for isolada.
            $registro = DB::transaction(fn () => Operacao::query()->create([
                'equipamento_id' => $equipamento->id,
                'operacao' => $operacao,
                'fila' => $operacao->fila(),
                'chave_idempotencia' => $chaveIdempotencia,
                'hash_payload' => $hash,
                'versao_contrato' => '1',
                'payload' => $payload,
                'agregado_tipo' => $agregadoTipo,
                'agregado_id' => $agregadoId,
                'correlation_id' => $correlationId ?? (string) Str::uuid7(),
                'causation_id' => $causationId,
                'estado' => EstadoOutbox::Pendente,
                'resultado' => ResultadoEquipamento::Pendente,
                'max_tentativas' => $operacao->permiteRetentativaAutomatica() ? (int) config('integracoes.outbox.max_tentativas') : 1,
                'disponivel_em' => now(),
                'expira_em' => $expiraEm,
                'origem' => $origem,
                'ator_id' => $ator?->id,
            ]));
        } catch (UniqueConstraintViolationException) {
            $concorrente = $this->buscar($operacao, $chaveIdempotencia)
                ?? throw new RegraIntegracaoViolada('outbox_conflito', 'Conflito ao registrar a operação de integração.');

            return $this->mesmaOperacaoOuFalha($concorrente, $hash, $chaveIdempotencia);
        }

        ProcessarOperacaoIntegracao::dispatch($registro->implantacao_id, $registro->id)
            ->onQueue($registro->fila)
            ->afterCommit();

        return $registro;
    }

    /** Publica novamente uma operação elegível (usado pelo despachante). */
    public function publicar(Operacao $operacao): void
    {
        ProcessarOperacaoIntegracao::dispatch($operacao->implantacao_id, $operacao->id)
            ->onQueue($operacao->fila)
            ->afterCommit();
    }

    private function buscar(OperacaoIntegracao $operacao, string $chave): ?Operacao
    {
        return Operacao::query()
            ->where('operacao', $operacao->value)
            ->where('chave_idempotencia', $chave)
            ->first();
    }

    private function mesmaOperacaoOuFalha(Operacao $existente, string $hash, string $chave): Operacao
    {
        if (! hash_equals($existente->hash_payload, $hash)) {
            throw new PayloadDivergente($chave);
        }

        return $existente;
    }

    /** @param  array<string, mixed>  $payload */
    private function hash(string $equipamentoId, OperacaoIntegracao $operacao, array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode([$equipamentoId, $operacao->value, $payload], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * O payload da outbox é mínimo (ADR-005 §15): nada de segredo, imagem,
     * template ou estruturas aninhadas que possam carregar payload bruto.
     *
     * @param  array<string, mixed>  $payload
     */
    private function garantirPayloadSeguro(array $payload): void
    {
        foreach ($payload as $chave => $valor) {
            if (! is_string($chave) || preg_match(self::CHAVES_PROIBIDAS, $chave)) {
                throw new RegraIntegracaoViolada('payload_proibido', "O campo '{$chave}' não pode ser enviado à outbox de integração.");
            }
            if (! is_scalar($valor) && $valor !== null) {
                throw new RegraIntegracaoViolada('payload_proibido', "O campo '{$chave}' deve ser um valor simples.");
            }
            if (is_string($valor) && mb_strlen($valor) > 500) {
                throw new RegraIntegracaoViolada('payload_proibido', "O campo '{$chave}' excede o tamanho permitido para a outbox.");
            }
        }
    }
}
