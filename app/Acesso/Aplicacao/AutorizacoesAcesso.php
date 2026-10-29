<?php

namespace App\Acesso\Aplicacao;

use App\Integracoes\Aplicacao\SincronizacaoCredenciais;
use App\Integracoes\Dominio\Enums\EstadoOutbox;
use App\Integracoes\Dominio\Enums\EstadoSincronizacao;
use App\Integracoes\Dominio\Enums\OperacaoIntegracao;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\AutorizacaoAcesso;
use App\Models\CredencialAcesso;
use App\Models\CredencialSincronizacao;
use App\Models\Imovel;
use App\Models\OperacaoIntegracao as Operacao;
use App\Models\Pessoa;
use App\Models\User;
use App\Models\Vinculo;
use App\Services\AuditService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Autorizações de acesso e o ciclo de vida das credenciais nos terminais
 * (AJ-006, fatias 6 e 7).
 *
 * - morador, inquilino e prestador: a autorização acompanha o vínculo;
 * - turista: cada visita é uma autorização nova, de no máximo 24 horas a
 *   partir do início aprovado. A pessoa continua no SDV; nada é apagado nem
 *   inativado no fim da visita;
 * - quando a autorização expira ou é encerrada, a credencial é revogada em
 *   cada terminal pela outbox (idempotente, com retentativa) e o pedido é
 *   repetido enquanto o terminal não confirmar a remoção.
 */
class AutorizacoesAcesso
{
    public const MOTIVO_EXPIRADA = 'autorizacao_expirada';

    public const MOTIVO_ENCERRADA = 'autorizacao_encerrada';

    /** Estados em que o terminal pode ainda guardar a credencial. */
    private const ESTADOS_COM_COPIA = [
        EstadoSincronizacao::Aguardando,
        EstadoSincronizacao::Processando,
        EstadoSincronizacao::Enviado,
        EstadoSincronizacao::Sincronizado,
        EstadoSincronizacao::AtualizacaoPendente,
        EstadoSincronizacao::IntervencaoNecessaria,
    ];

    public function __construct(private SincronizacaoCredenciais $sincronizacao, private AuditService $audit) {}

    /** Validade máxima da credencial do turista, em horas (nunca acima de 24). */
    public static function validadeTuristaHoras(): int
    {
        return max(1, min(24, (int) config('acesso.turista.validade_horas', 24)));
    }

    /** Autorização vigente mais recente da pessoa, se houver. */
    public function vigentePara(Pessoa $pessoa): ?AutorizacaoAcesso
    {
        return AutorizacaoAcesso::query()
            ->where('pessoa_id', $pessoa->id)
            ->where('status', 'ativa')
            ->where(fn ($q) => $q->whereNull('fim')->orWhere('fim', '>', now()))
            ->latest('inicio')
            ->first();
    }

    /** Autorização que acompanha um vínculo ativo; reaproveita a que já existir. */
    public function paraVinculo(Vinculo $vinculo, ?User $ator = null): AutorizacaoAcesso
    {
        if ($vinculo->status !== 'ativo' || ($vinculo->ended_at !== null && $vinculo->ended_at->isPast())) {
            throw new RegraIntegracaoViolada('vinculo_inativo', 'O vínculo da pessoa não está ativo.');
        }

        $existente = AutorizacaoAcesso::query()
            ->where('vinculo_id', $vinculo->id)
            ->where('status', 'ativa')
            ->first();
        if ($existente !== null) {
            return $existente;
        }

        $autorizacao = AutorizacaoAcesso::query()->create([
            'pessoa_id' => $vinculo->pessoa_id,
            'vinculo_id' => $vinculo->id,
            'imovel_id' => $vinculo->imovel_id,
            'tipo' => AutorizacaoAcesso::TIPO_VINCULO,
            'inicio' => $vinculo->started_at ?? now(),
            'fim' => $vinculo->ended_at,
            'status' => 'ativa',
            'created_by' => $ator?->id,
        ]);
        $this->auditar('autorizacao_acesso_criada', $autorizacao);

        return $autorizacao;
    }

    /**
     * Nova visita de turista: autorização própria, com início aprovado agora
     * (ou na data pedida, se for futura) e fim em no máximo 24 horas.
     */
    public function registrarVisitaTurista(
        Pessoa $pessoa,
        ?Imovel $imovel,
        string $responsavel,
        ?CarbonImmutable $inicioPedido,
        ?CarbonImmutable $fimPedido,
        ?User $ator = null,
    ): AutorizacaoAcesso {
        if ($pessoa->status !== 'ativo') {
            throw new RegraIntegracaoViolada('pessoa_inativa', 'A pessoa não está ativa no SDV.');
        }

        $agora = CarbonImmutable::now();
        $inicio = $inicioPedido !== null && $inicioPedido->greaterThan($agora) ? $inicioPedido : $agora;
        $fim = $inicio->addHours(self::validadeTuristaHoras());
        if ($fimPedido !== null && $fimPedido->lessThan($fim)) {
            $fim = $fimPedido;
        }
        if ($fim->lessThanOrEqualTo($inicio)) {
            throw new RegraIntegracaoViolada('periodo_invalido', 'O fim da visita precisa ser depois do início.');
        }

        return DB::transaction(function () use ($pessoa, $imovel, $responsavel, $inicio, $fim, $ator): AutorizacaoAcesso {
            Pessoa::query()->whereKey($pessoa->id)->lockForUpdate()->first();

            $emCurso = AutorizacaoAcesso::query()
                ->where('pessoa_id', $pessoa->id)
                ->where('tipo', AutorizacaoAcesso::TIPO_TURISTA)
                ->where('status', 'ativa')
                ->where('fim', '>', now())
                ->first();
            if ($emCurso !== null) {
                throw new RegraIntegracaoViolada('visita_vigente', 'Esta pessoa já tem uma visita vigente até '.$emCurso->fim->format('d/m/Y H:i').'.');
            }

            $autorizacao = AutorizacaoAcesso::query()->create([
                'pessoa_id' => $pessoa->id,
                'imovel_id' => $imovel?->id,
                'tipo' => AutorizacaoAcesso::TIPO_TURISTA,
                'inicio' => $inicio,
                'fim' => $fim,
                'status' => 'ativa',
                'responsavel' => mb_substr(trim($responsavel), 0, 160) ?: null,
                'created_by' => $ator?->id,
            ]);
            $this->auditar('autorizacao_turista_criada', $autorizacao);

            return $autorizacao;
        });
    }

    /** A credencial da autorização, criada uma única vez. */
    public function credencialDe(AutorizacaoAcesso $autorizacao): CredencialAcesso
    {
        return CredencialAcesso::query()->firstOrCreate(
            ['autorizacao_id' => $autorizacao->id, 'tipo' => CredencialAcesso::TIPO_CADASTRO],
            [
                'pessoa_id' => $autorizacao->pessoa_id,
                'vigencia_inicio' => $autorizacao->inicio,
                'vigencia_fim' => $autorizacao->fim,
                'status' => 'ativa',
            ],
        );
    }

    /** Encerra antes do fim (saída antecipada, erro de cadastro) e revoga nos terminais. */
    public function encerrar(AutorizacaoAcesso $autorizacao, string $motivo, User $ator): AutorizacaoAcesso
    {
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 5) {
            throw new RegraIntegracaoViolada('motivo_obrigatorio', 'Informe o motivo do encerramento (mínimo 5 caracteres).');
        }

        DB::transaction(function () use ($autorizacao, $motivo, $ator): void {
            $atual = AutorizacaoAcesso::query()->lockForUpdate()->findOrFail($autorizacao->id);
            if ($atual->status !== 'ativa') {
                return;
            }
            $atual->forceFill([
                'status' => 'encerrada',
                'encerrada_em' => now(),
                'encerrada_por' => $ator->id,
                'motivo_encerramento' => mb_substr($motivo, 0, 200),
                'versao' => $atual->versao + 1,
            ])->save();
            CredencialAcesso::query()->where('autorizacao_id', $atual->id)->where('status', 'ativa')
                ->update(['status' => 'revogada', 'revogada_em' => now()]);
            $this->auditar('autorizacao_acesso_encerrada', $atual, $motivo);
        });

        $this->revogarNosTerminais($autorizacao->refresh(), self::MOTIVO_ENCERRADA);

        return $autorizacao->refresh();
    }

    /**
     * Rotina da implantação corrente: expira o que venceu e (re)pede a
     * revogação de toda credencial encerrada que algum terminal ainda pode
     * guardar. Pode rodar quantas vezes for preciso: os pedidos são
     * idempotentes e só se repetem depois de esgotadas as tentativas.
     *
     * @return array{expiradas: int, revogacoes: int}
     */
    public function processarVencimentos(): array
    {
        $expiradas = 0;
        AutorizacaoAcesso::query()
            ->where('status', 'ativa')
            ->whereNotNull('fim')
            ->where('fim', '<=', now())
            ->orderBy('fim')
            ->each(function (AutorizacaoAcesso $autorizacao) use (&$expiradas): void {
                DB::transaction(function () use ($autorizacao, &$expiradas): void {
                    $atual = AutorizacaoAcesso::query()->lockForUpdate()->find($autorizacao->id);
                    if ($atual === null || $atual->status !== 'ativa') {
                        return;
                    }
                    $atual->forceFill(['status' => 'expirada', 'encerrada_em' => now(), 'versao' => $atual->versao + 1])->save();
                    CredencialAcesso::query()->where('autorizacao_id', $atual->id)->where('status', 'ativa')
                        ->update(['status' => 'expirada', 'revogada_em' => now()]);
                    $this->auditar('autorizacao_acesso_expirada', $atual);
                    $expiradas++;
                });
            });

        $revogacoes = 0;
        AutorizacaoAcesso::query()
            ->whereIn('status', ['expirada', 'encerrada', 'cancelada'])
            ->whereHas('credenciais.sincronizacoes', fn ($q) => $q->whereIn('estado', array_map(fn ($e) => $e->value, self::ESTADOS_COM_COPIA)))
            ->each(function (AutorizacaoAcesso $autorizacao) use (&$revogacoes): void {
                $revogacoes += $this->revogarNosTerminais($autorizacao, $autorizacao->status === 'expirada' ? self::MOTIVO_EXPIRADA : self::MOTIVO_ENCERRADA);
            });

        return ['expiradas' => $expiradas, 'revogacoes' => $revogacoes];
    }

    /** @return int pedidos de revogação novos */
    private function revogarNosTerminais(AutorizacaoAcesso $autorizacao, string $motivo): int
    {
        $novos = 0;
        $sincronizacoes = CredencialSincronizacao::query()
            ->whereIn('credencial_id', CredencialAcesso::query()->where('autorizacao_id', $autorizacao->id)->select('id'))
            ->whereIn('estado', array_map(fn ($e) => $e->value, self::ESTADOS_COM_COPIA))
            ->with('equipamento')
            ->get();

        foreach ($sincronizacoes as $sincronizacao) {
            $tentativa = $this->proximaTentativaDeRevogacao($sincronizacao);
            if ($tentativa === null) {
                continue;
            }

            try {
                $operacao = $this->sincronizacao->solicitarRevogacao(
                    $sincronizacao->equipamento,
                    $sincronizacao->credencial_id,
                    $motivo,
                    "revogacao:{$sincronizacao->credencial_id}:{$sincronizacao->equipamento_id}:{$tentativa}",
                );
                $novos += $operacao->wasRecentlyCreated ? 1 : 0;
            } catch (RegraIntegracaoViolada $e) {
                // Terminal inativado: fica visível como pendência, sem dado pessoal no log.
                Log::warning('acesso.revogacao_nao_solicitada', [
                    'credencial_id' => $sincronizacao->credencial_id,
                    'equipamento_id' => $sincronizacao->equipamento_id,
                    'codigo' => $e->codigo,
                ]);
            }
        }

        return $novos;
    }

    /**
     * Número do próximo pedido de revogação, ou null quando já há um pedido
     * em curso, concluído, ou falho há pouco tempo. Sem comunicação, o pedido
     * esgotado é repetido depois do intervalo configurado.
     */
    private function proximaTentativaDeRevogacao(CredencialSincronizacao $sincronizacao): ?int
    {
        $revogacoes = Operacao::query()
            ->where('equipamento_id', $sincronizacao->equipamento_id)
            ->where('agregado_id', $sincronizacao->credencial_id)
            ->where('operacao', OperacaoIntegracao::RevogarCredencial->value);
        $ultima = (clone $revogacoes)->latest('created_at')->first();

        if ($ultima === null) {
            return 1;
        }
        if (! in_array($ultima->estado, [EstadoOutbox::IntervencaoNecessaria, EstadoOutbox::Cancelado], true)) {
            // Pendente, em processamento, com retentativa agendada ou já
            // respondida pelo terminal (inclusive recusa definitiva).
            return null;
        }
        $intervalo = max(1, (int) config('acesso.revogacao.repetir_apos_minutos', 15));
        if ($ultima->updated_at !== null && $ultima->updated_at->greaterThan(now()->subMinutes($intervalo))) {
            return null;
        }

        return $revogacoes->count() + 1;
    }

    private function auditar(string $acao, AutorizacaoAcesso $autorizacao, ?string $motivo = null): void
    {
        $this->audit->record(
            action: $acao,
            module: 'acessos',
            entityType: 'autorizacoes_acesso',
            entityId: $autorizacao->id,
            justification: $motivo,
            metadata: [
                'pessoa_id' => $autorizacao->pessoa_id,
                'tipo' => $autorizacao->tipo,
                'inicio' => $autorizacao->inicio?->toIso8601String(),
                'fim' => $autorizacao->fim?->toIso8601String(),
                'status' => $autorizacao->status,
            ],
        );
    }
}
