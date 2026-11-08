<?php

namespace App\Acesso\Aplicacao;

use App\Acesso\Dominio\ResultadoEnvioFacial;
use App\Integracoes\Aplicacao\OutboxIntegracao;
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
use App\Models\FotoFacialSincronizacao;
use App\Models\OperacaoIntegracao as Operacao;
use App\Models\Pessoa;
use App\Models\PessoaArquivo;
use App\Models\Posto;
use App\Models\User;
use App\Models\Vinculo;
use App\Services\AuditService;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Envio de uma pessoa já salva a um terminal facial (AJ-006, fatias 6 e 7).
 *
 * - só pessoa salva e com autorização vigente no SDV (RN-040); o terminal
 *   nunca decide quem pode entrar;
 * - o pedido vai pela outbox com chave idempotente: clicar duas vezes ou
 *   repetir com o mesmo conteúdo não cria um segundo comando;
 * - é cadastro no terminal, não abertura: nada aqui aciona relé;
 * - nenhum template é gerado ou guardado pelo SDV.
 *
 * AJ-010 (ADR-013 1.1.0): depois do cadastro, a foto atual da pessoa vira
 * um segundo pedido, com estado próprio. Ela só vai ao terminal com a flag
 * `integracoes.foto_facial_habilitada` e a capacidade `credencial_facial`
 * homologada; caso contrário fica salva no SDV e o envio aparece como
 * pendente de habilitação. "Cadastro confirmado" nunca é "face sincronizada".
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
        private OutboxIntegracao $outbox,
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
            return $this->resultado($existente, $terminal, $credencial, 'ja_solicitado', $avisos)
                ->comFoto($this->solicitarFoto($pessoa, $terminal, $credencial, $ator));
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

        return $this->resultado($operacao, $terminal, $credencial, 'solicitado', $avisos)
            ->comFoto($this->solicitarFoto($pessoa, $terminal, $credencial, $ator));
    }

    /**
     * Segundo pedido do envio: a foto atual da pessoa (AJ-010). Idempotente por
     * credencial, terminal e versão da foto; a outbox leva só referências.
     *
     * @return array{situacao: string, estado: ?string, mensagem: string}
     */
    public function solicitarFoto(Pessoa $pessoa, Equipamento $terminal, CredencialAcesso $credencial, User $ator): array
    {
        $link = PessoaArquivo::query()
            ->where('pessoa_id', $pessoa->id)
            ->where('category', 'foto')
            ->where('is_current', true)
            ->with('file')
            ->latest('linked_at')
            ->first();
        if ($link === null || ! $link->file?->isAvailable()) {
            return ['situacao' => 'sem_foto', 'estado' => null, 'mensagem' => 'A pessoa não tem foto atual no SDV: o terminal recebe só o cadastro, sem face.'];
        }
        $arquivo = $link->file;

        $bloqueio = match (true) {
            ! config('integracoes.foto_facial_habilitada') => ['pendente_habilitacao', 'Foto salva no SDV; envio facial pendente de habilitação/homologação.'],
            ! $terminal->suporta(Capacidade::CredencialFacial) => ['pendente_homologacao', 'Foto salva no SDV; o envio de foto ainda não está homologado para este terminal.'],
            default => null,
        };

        $pedido = DB::transaction(function () use ($pessoa, $terminal, $credencial, $ator, $arquivo, $bloqueio): array {
            $registro = FotoFacialSincronizacao::query()->lockForUpdate()
                ->where('equipamento_id', $terminal->id)
                ->where('credencial_id', $credencial->id)
                ->first();

            if ($bloqueio !== null) {
                $this->registrarFoto($registro, $pessoa, $terminal, $credencial, $arquivo->id, $arquivo->checksum_sha256, EstadoSincronizacao::NaoEnviado, null, $bloqueio[1]);

                return ['situacao' => $bloqueio[0], 'estado' => EstadoSincronizacao::NaoEnviado->value, 'mensagem' => $bloqueio[1]];
            }

            if ($registro !== null && $registro->versao_foto === $arquivo->checksum_sha256 && in_array($registro->estado, self::ESTADOS_EM_CURSO, true)) {
                return ['situacao' => 'ja_solicitado', 'estado' => $registro->estado->value, 'mensagem' => 'O envio desta mesma foto já tinha sido pedido e não foi repetido.'];
            }

            try {
                $tentativa = Operacao::query()
                    ->where('equipamento_id', $terminal->id)
                    ->where('agregado_id', $credencial->id)
                    ->where('operacao', OperacaoIntegracao::EnviarFotoFacial->value)
                    ->count() + 1;
                $operacao = $this->outbox->registrar(
                    $terminal,
                    OperacaoIntegracao::EnviarFotoFacial,
                    [
                        'credencial_id' => $credencial->id,
                        'pessoa_id' => $pessoa->id,
                        'arquivo_id' => $arquivo->id,
                        'versao_arquivo' => $arquivo->checksum_sha256,
                    ],
                    "foto-facial:{$credencial->id}:{$terminal->id}:{$arquivo->checksum_sha256}:{$tentativa}",
                    agregadoTipo: 'credencial',
                    agregadoId: $credencial->id,
                    ator: $ator,
                    expiraEm: $credencial->vigencia_fim,
                );
            } catch (RegraIntegracaoViolada $e) {
                return ['situacao' => 'recusado', 'estado' => null, 'mensagem' => $e->getMessage()];
            }

            $this->registrarFoto($registro, $pessoa, $terminal, $credencial, $arquivo->id, $arquivo->checksum_sha256, EstadoSincronizacao::Aguardando, $operacao->id, null);
            $this->audit->record(
                action: 'facial_foto_envio_solicitado',
                module: 'pessoas',
                entityType: 'pessoas',
                entityId: $pessoa->id,
                classification: 'restrita',
                metadata: ['equipamento_id' => $terminal->id, 'credencial_id' => $credencial->id, 'arquivo_id' => $arquivo->id, 'operacao_id' => $operacao->id],
            );

            return ['situacao' => 'solicitado', 'estado' => EstadoSincronizacao::Aguardando->value, 'mensagem' => 'Envio da foto registrado; o terminal recebe a foto depois de confirmar o cadastro.'];
        });

        if (! in_array($pedido['situacao'], ['solicitado', 'ja_solicitado'], true)) {
            return $pedido;
        }

        // Com fila síncrona o processamento já aconteceu: mostrar o estado comprovado.
        $registro = FotoFacialSincronizacao::query()->where('equipamento_id', $terminal->id)->where('credencial_id', $credencial->id)->first();
        $estado = $registro?->estado->value ?? $pedido['estado'];
        $mensagem = match ($estado) {
            'sincronizado' => 'O terminal confirmou o recebimento da foto desta versão.',
            'falha', 'intervencao_necessaria' => 'O terminal não aceitou a foto: '.($registro->erro_sanitizado ?: 'motivo não informado').' Cadastro e foto continuam salvos no SDV.',
            'atualizacao_pendente' => 'O terminal não confirmou a foto. O SDV vai conferir antes de reenviar.',
            default => $pedido['mensagem'],
        };

        return ['situacao' => $pedido['situacao'], 'estado' => $estado, 'mensagem' => $mensagem];
    }

    /** Estado atual da foto num terminal, relido depois do processamento (para a tela). */
    public function estadoFoto(string $credencialId, string $terminalId): ?string
    {
        return FotoFacialSincronizacao::query()
            ->where('equipamento_id', $terminalId)
            ->where('credencial_id', $credencialId)
            ->first()?->estado->value;
    }

    private function registrarFoto(?FotoFacialSincronizacao $registro, Pessoa $pessoa, Equipamento $terminal, CredencialAcesso $credencial, string $arquivoId, string $versao, EstadoSincronizacao $estado, ?string $operacaoId, ?string $erro): void
    {
        $registro ??= new FotoFacialSincronizacao(['equipamento_id' => $terminal->id, 'credencial_id' => $credencial->id, 'versao' => 0]);
        $registro->forceFill([
            'pessoa_id' => $pessoa->id,
            'arquivo_id' => $arquivoId,
            'versao_foto' => $versao,
            'estado' => $estado,
            'ultima_operacao_id' => $operacaoId ?? $registro->ultima_operacao_id,
            'erro_sanitizado' => $erro,
            'versao' => ($registro->versao ?? 0) + 1,
        ])->save();
    }

    /**
     * Situação da pessoa em cada terminal, para a tela.
     *
     * @return Collection<int, array{terminal: string, estado: string, foto: ?string, atualizado: ?string}>
     */
    public function situacao(Pessoa $pessoa): Collection
    {
        $fotos = FotoFacialSincronizacao::query()->where('pessoa_id', $pessoa->id)->get()
            ->keyBy(fn (FotoFacialSincronizacao $f) => $f->equipamento_id.'|'.$f->credencial_id);

        return CredencialSincronizacao::query()
            ->whereIn('credencial_id', CredencialAcesso::query()->where('pessoa_id', $pessoa->id)->select('id'))
            ->with('equipamento')
            ->latest('updated_at')
            ->get()
            ->map(fn (CredencialSincronizacao $s) => [
                'terminal' => $s->equipamento->nome,
                'estado' => $s->estado->value,
                'foto' => $fotos->get($s->equipamento_id.'|'.$s->credencial_id)?->estado->value,
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
            'sincronizado' => "O terminal {$local} confirmou o cadastro da pessoa (usuário e validade).",
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
