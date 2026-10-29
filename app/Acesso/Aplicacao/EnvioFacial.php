<?php

namespace App\Acesso\Aplicacao;

use App\Acesso\Dominio\ResultadoEnvioFacial;
use App\Integracoes\Aplicacao\SincronizacaoCredenciais;
use App\Integracoes\Aplicacao\TurnosOperacionais;
use App\Integracoes\Dominio\Dados\CredencialParaSincronizar;
use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\Direcao;
use App\Integracoes\Dominio\Enums\EstadoSincronizacao;
use App\Integracoes\Dominio\Enums\OperacaoIntegracao;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Integracoes\Infra\RegistroAdaptadores;
use App\Models\AutorizacaoAcesso;
use App\Models\CredencialAcesso;
use App\Models\CredencialSincronizacao;
use App\Models\Equipamento;
use App\Models\EquipamentoEventoRecebido;
use App\Models\OperacaoIntegracao as Operacao;
use App\Models\Pessoa;
use App\Models\Posto;
use App\Models\User;
use App\Models\Vinculo;
use App\Services\AuditService;
use DateTimeImmutable;
use Illuminate\Support\Collection;

/**
 * Envio de uma pessoa já salva a um terminal facial (AJ-006, fatias 6 e 7).
 *
 * - só pessoa salva e com autorização vigente no SDV (RN-040); o terminal
 *   nunca decide quem pode entrar;
 * - o pedido vai pela outbox com chave idempotente: clicar duas vezes ou
 *   repetir com o mesmo conteúdo não cria um segundo comando;
 * - é cadastro no terminal, não abertura: nada aqui aciona relé;
 * - sem foto nem template: a credencial facial segue bloqueada (ADR-013).
 */
class EnvioFacial
{
    /** Estados em que um pedido igual já está a caminho ou concluído. */
    private const ESTADOS_EM_CURSO = [
        EstadoSincronizacao::Aguardando,
        EstadoSincronizacao::Processando,
        EstadoSincronizacao::Enviado,
        EstadoSincronizacao::Sincronizado,
        EstadoSincronizacao::AtualizacaoPendente,
    ];

    public function __construct(
        private AutorizacoesAcesso $autorizacoes,
        private SincronizacaoCredenciais $sincronizacao,
        private RegistroAdaptadores $adaptadores,
        private TurnosOperacionais $turnos,
        private AuditService $audit,
    ) {}

    /**
     * Terminais que podem receber cadastro agora: ativos, com ponto vigente
     * e adaptador permitido. Os do posto do turno vêm primeiro.
     *
     * @return Collection<int, array{id: string, nome: string, ponto: string, do_posto: bool, simulador: bool}>
     */
    public function terminaisPara(User $operador): Collection
    {
        $turno = $this->turnos->turnoAtivo($operador);
        $pontosDoPosto = $turno !== null
            ? (Posto::query()->find($turno->posto_id)?->pontosVigentes()->pluck('pontos_acesso.id')->all() ?? [])
            : [];

        return Equipamento::query()
            ->terminais()
            ->where('status', StatusEquipamento::Ativo->value)
            ->whereHas('vinculoVigente')
            ->with('vinculoVigente.pontoAcesso')
            ->orderBy('nome')
            ->get()
            ->filter(fn (Equipamento $terminal) => $this->adaptadores->permitido($terminal->adaptador))
            ->map(fn (Equipamento $terminal) => [
                'id' => $terminal->id,
                'nome' => $terminal->nome,
                'ponto' => $terminal->vinculoVigente->pontoAcesso->nome,
                'do_posto' => in_array($terminal->vinculoVigente->ponto_acesso_id, $pontosDoPosto, true),
                'simulador' => $terminal->adaptador === 'simulador',
            ])
            ->sortBy(fn (array $t) => ($t['do_posto'] ? '0' : '1').$t['nome'])
            ->values();
    }

    public function enviar(Pessoa $pessoa, string $terminalId, User $ator, ?AutorizacaoAcesso $autorizacao = null): ResultadoEnvioFacial
    {
        $terminal = $this->terminaisPara($ator)->firstWhere('id', $terminalId) !== null
            ? Equipamento::query()->terminais()->with('vinculoVigente.pontoAcesso')->find($terminalId)
            : null;
        if ($terminal === null) {
            return $this->recusar($pessoa, $terminalId, 'terminal_indisponivel', 'Escolha um terminal ativo e vinculado a um ponto de acesso.');
        }
        if ($pessoa->status !== 'ativo') {
            return $this->recusar($pessoa, $terminal->id, 'pessoa_inativa', 'A pessoa não está ativa no SDV.');
        }

        try {
            $autorizacao ??= $this->autorizacaoPara($pessoa, $ator);
        } catch (RegraIntegracaoViolada $e) {
            return $this->recusar($pessoa, $terminal->id, $e->codigo, $e->getMessage());
        }
        if ($autorizacao === null) {
            return $this->recusar($pessoa, $terminal->id, 'sem_autorizacao', 'A pessoa não tem autorização vigente. Confira o vínculo com o imóvel ou registre a visita.');
        }
        if ($autorizacao->pessoa_id !== $pessoa->id || ! $autorizacao->vigente()) {
            return $this->recusar($pessoa, $terminal->id, 'autorizacao_encerrada', 'A autorização desta pessoa está encerrada. Uma nova visita precisa de nova autorização.');
        }

        $avisos = [];
        if ($autorizacao->tipo === AutorizacaoAcesso::TIPO_TURISTA) {
            $relogio = $this->conferirRelogio($terminal);
            if ($relogio['bloqueio'] !== null) {
                return $this->recusar($pessoa, $terminal->id, $relogio['bloqueio'][0], $relogio['bloqueio'][1]);
            }
            $avisos = $relogio['avisos'];
            if (! $terminal->suporta(Capacidade::ValidadeNativa)) {
                $avisos[] = 'Este terminal não aplica sozinho o fim da validade: o SDV pede a remoção no fim da visita e repete o pedido se o terminal estiver sem comunicação.';
            }
        }

        $credencial = $this->autorizacoes->credencialDe($autorizacao);
        $dados = new CredencialParaSincronizar(
            credencialId: $credencial->id,
            tipo: CredencialAcesso::TIPO_CADASTRO,
            titularReferencia: $pessoa->id,
            nomeExibicao: $pessoa->nomeExibicao(),
            vigenciaInicio: DateTimeImmutable::createFromInterface($credencial->vigencia_inicio),
            vigenciaFim: $credencial->vigencia_fim !== null ? DateTimeImmutable::createFromInterface($credencial->vigencia_fim) : null,
            direcao: $terminal->direcao ?? Direcao::Entrada,
        );

        $existente = $this->pedidoIgualEmCurso($terminal, $credencial, $dados);
        if ($existente !== null) {
            return $this->resultado($existente, $terminal, $credencial, 'ja_solicitado', $avisos);
        }

        try {
            $operacao = $this->sincronizacao->solicitarSincronizacao(
                $terminal,
                $dados,
                "envio-facial:{$credencial->id}:{$terminal->id}:".$this->proximaTentativa($terminal, $credencial),
                $ator,
            );
        } catch (RegraIntegracaoViolada $e) {
            return $this->recusar($pessoa, $terminal->id, $e->codigo, $e->getMessage());
        }

        $this->audit->record(
            action: 'facial_envio_solicitado',
            module: 'pessoas',
            entityType: 'pessoas',
            entityId: $pessoa->id,
            metadata: [
                'equipamento_id' => $terminal->id,
                'credencial_id' => $credencial->id,
                'autorizacao_id' => $autorizacao->id,
                'operacao_id' => $operacao->id,
                'tipo_autorizacao' => $autorizacao->tipo,
            ],
        );

        return $this->resultado($operacao, $terminal, $credencial, 'solicitado', $avisos);
    }

    /**
     * Situação da pessoa em cada terminal, para a tela.
     *
     * @return Collection<int, array{terminal: string, estado: string, atualizado: ?string}>
     */
    public function situacao(Pessoa $pessoa): Collection
    {
        return CredencialSincronizacao::query()
            ->whereIn('credencial_id', CredencialAcesso::query()->where('pessoa_id', $pessoa->id)->select('id'))
            ->with('equipamento')
            ->latest('updated_at')
            ->get()
            ->map(fn (CredencialSincronizacao $s) => [
                'terminal' => $s->equipamento->nome,
                'estado' => $s->estado->value,
                'atualizado' => $s->updated_at?->format('d/m/Y H:i'),
            ]);
    }

    /**
     * Fuso do servidor e relógio do terminal (fatia 7). Divergência
     * conhecida bloqueia o envio de credencial com hora para acabar.
     *
     * @return array{bloqueio: array{0: string, 1: string}|null, avisos: list<string>}
     */
    public function conferirRelogio(Equipamento $terminal): array
    {
        $fuso = (string) config('acesso.fuso_operacional');
        if (date_default_timezone_get() !== $fuso || config('app.timezone') !== $fuso) {
            return ['bloqueio' => ['fuso_servidor_divergente', "O servidor não está no fuso da portaria ({$fuso}). Corrija a configuração antes de enviar credenciais com hora marcada."], 'avisos' => []];
        }

        $ultimo = EquipamentoEventoRecebido::query()
            ->where('equipamento_id', $terminal->id)
            ->whereNotNull('ocorrido_no_equipamento_em')
            ->where('recebido_em', '>=', now()->subDay())
            ->latest('recebido_em')
            ->first();

        if ($ultimo === null) {
            return ['bloqueio' => null, 'avisos' => ['O relógio deste terminal ainda não foi conferido hoje (nenhum evento recebido nas últimas 24 horas).']];
        }
        if ($ultimo->divergencia_relogio_segundos !== null) {
            $minutos = (int) round(abs($ultimo->divergencia_relogio_segundos) / 60);

            return ['bloqueio' => ['relogio_terminal_divergente', "O relógio do terminal está {$minutos} min diferente do servidor. Acerte a hora do terminal antes de enviar a visita."], 'avisos' => []];
        }

        return ['bloqueio' => null, 'avisos' => []];
    }

    private function autorizacaoPara(Pessoa $pessoa, User $ator): ?AutorizacaoAcesso
    {
        $vigente = $this->autorizacoes->vigentePara($pessoa);
        if ($vigente !== null) {
            return $vigente;
        }

        $vinculo = Vinculo::query()
            ->where('pessoa_id', $pessoa->id)
            ->where('status', 'ativo')
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', now()))
            ->latest('started_at')
            ->first();

        return $vinculo !== null ? $this->autorizacoes->paraVinculo($vinculo, $ator) : null;
    }

    private function pedidoIgualEmCurso(Equipamento $terminal, CredencialAcesso $credencial, CredencialParaSincronizar $dados): ?Operacao
    {
        $sincronizacao = CredencialSincronizacao::query()
            ->where('equipamento_id', $terminal->id)
            ->where('credencial_id', $credencial->id)
            ->first();
        if ($sincronizacao === null || ! in_array($sincronizacao->estado, self::ESTADOS_EM_CURSO, true)) {
            return null;
        }

        $ultima = Operacao::query()->find($sincronizacao->ultima_operacao_id);

        return $ultima !== null
            && $ultima->operacao === OperacaoIntegracao::SincronizarCredencial
            && $ultima->payload == SincronizacaoCredenciais::payloadDe($dados)
            ? $ultima
            : null;
    }

    private function proximaTentativa(Equipamento $terminal, CredencialAcesso $credencial): int
    {
        return Operacao::query()
            ->where('equipamento_id', $terminal->id)
            ->where('agregado_id', $credencial->id)
            ->where('operacao', OperacaoIntegracao::SincronizarCredencial->value)
            ->count() + 1;
    }

    /** @param  list<string>  $avisos */
    private function resultado(Operacao $operacao, Equipamento $terminal, CredencialAcesso $credencial, string $situacao, array $avisos): ResultadoEnvioFacial
    {
        $estado = CredencialSincronizacao::query()
            ->where('equipamento_id', $terminal->id)
            ->where('credencial_id', $credencial->id)
            ->first();
        $valor = $estado?->estado->value ?? EstadoSincronizacao::Aguardando->value;
        $local = "{$terminal->nome} ({$terminal->vinculoVigente->pontoAcesso->nome})";

        $mensagem = match ($valor) {
            'sincronizado' => "O terminal {$local} confirmou o cadastro.",
            'falha' => "O terminal {$local} não aceitou o cadastro: ".($estado->erro_sanitizado ?: 'motivo não informado').' O cadastro no SDV continua salvo.',
            'intervencao_necessaria' => "Sem resposta do terminal {$local} depois das tentativas. O cadastro no SDV continua salvo; verifique o equipamento e envie de novo.",
            'atualizacao_pendente' => "O terminal {$local} não confirmou a gravação. O SDV vai conferir antes de reenviar.",
            default => "Pedido registrado para o terminal {$local}. Acompanhe a situação nesta tela.",
        };
        if ($situacao === 'ja_solicitado') {
            $mensagem = 'Este envio já tinha sido pedido e não foi repetido. '.$mensagem;
        }

        return new ResultadoEnvioFacial($situacao, $situacao, $mensagem, $valor, $operacao->id, $credencial->id, $avisos);
    }

    private function recusar(Pessoa $pessoa, string $terminalId, string $codigo, string $mensagem): ResultadoEnvioFacial
    {
        $this->audit->record(
            action: 'facial_envio_recusado',
            module: 'pessoas',
            entityType: 'pessoas',
            entityId: $pessoa->id,
            result: 'negado',
            reasonCode: $codigo,
            metadata: ['equipamento_id' => $terminalId],
        );

        return ResultadoEnvioFacial::recusado($codigo, $mensagem);
    }
}
