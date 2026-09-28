<?php

namespace App\Integracoes\Aplicacao;

use App\Integracoes\Dominio\Dados\CapacidadesDeclaradas;
use App\Integracoes\Dominio\Dados\ComandoAbertura;
use App\Integracoes\Dominio\Dados\CredencialParaSincronizar;
use App\Integracoes\Dominio\Dados\ResultadoColetaEventos;
use App\Integracoes\Dominio\Dados\ResultadoOperacao;
use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\Direcao;
use App\Integracoes\Dominio\Enums\EstadoOutbox;
use App\Integracoes\Dominio\Enums\EstadoSaude;
use App\Integracoes\Dominio\Enums\EstadoSincronizacao;
use App\Integracoes\Dominio\Enums\OperacaoIntegracao;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Integracoes\Infra\RegistroAdaptadores;
use App\Integracoes\Infra\SanitizadorIntegracao;
use App\Models\CredencialSincronizacao;
use App\Models\Equipamento;
use App\Models\EquipamentoCapacidade;
use App\Models\IntegracaoOcorrencia;
use App\Models\OperacaoIntegracao as Operacao;
use App\Models\ReferenciaExterna;
use App\Services\AuditService;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Consumidor da outbox de integração (ADR-004 §20-23, ADR-005 §16-21,
 * ADR-007 §8-10).
 *
 * 1. reserva a operação com lease (um só processamento normal por vez);
 * 2. revalida estado do equipamento, validade, capacidade e políticas;
 * 3. chama a porta fora de transação;
 * 4. grava resultado, efeitos, ocorrência e auditoria numa transação curta.
 *
 * Timeout é "confirmação desconhecida", nunca sucesso (RN-080). Abertura
 * remota nunca é repetida automaticamente.
 */
class ProcessadorOperacoes
{
    public function __construct(
        private RegistroAdaptadores $adaptadores,
        private FabricaContextoEquipamento $contextos,
        private SanitizadorIntegracao $sanitizador,
        private ColetorEventos $coletor,
        private AuditService $audit,
    ) {}

    public function processar(string $operacaoId): ?Operacao
    {
        $lease = $this->reservar($operacaoId);
        if ($lease === null) {
            return null;
        }

        $operacao = Operacao::query()->findOrFail($operacaoId);
        $equipamento = $operacao->equipamento;
        $inicio = hrtime(true);

        $bloqueio = $this->bloqueioAntesDoEnvio($operacao, $equipamento);
        $resultado = $bloqueio ?? $this->executar($operacao, $equipamento);
        $latencia = (int) ((hrtime(true) - $inicio) / 1_000_000);

        DB::transaction(function () use ($operacao, $equipamento, $resultado, $bloqueio, $latencia, $lease): void {
            $operacao = Operacao::query()->lockForUpdate()->findOrFail($operacao->id);
            if ($operacao->lease_token !== $lease) {
                // Lease expirou e outro worker assumiu; não sobrescrever.
                return;
            }

            $this->aplicarEfeitos($operacao, $equipamento, $resultado, contatouEquipamento: $bloqueio === null);
            $this->concluir($operacao, $resultado);
            $this->registrarOcorrencia($operacao, $resultado, $latencia);
            $this->auditar($operacao, $resultado);
        });

        return $operacao->refresh();
    }

    // ---- Reserva ----------------------------------------------------------

    private function reservar(string $operacaoId): ?string
    {
        return DB::transaction(function () use ($operacaoId): ?string {
            $operacao = Operacao::query()->lockForUpdate()->find($operacaoId);
            if ($operacao === null) {
                return null;
            }

            $agora = now();
            $elegivel = in_array($operacao->estado->value, EstadoOutbox::elegiveis(), true)
                && $operacao->disponivel_em <= $agora;
            $leaseVencido = $operacao->estado === EstadoOutbox::Processando
                && $operacao->lease_ate !== null && $operacao->lease_ate < $agora;

            if (! $elegivel && ! $leaseVencido) {
                return null;
            }

            if ($leaseVencido && ! $operacao->operacao->permiteRetentativaAutomatica()) {
                // O worker anterior morreu no meio de uma abertura: não se sabe
                // se o terminal recebeu. Não reenviar; exigir intervenção.
                $operacao->forceFill([
                    'estado' => EstadoOutbox::IntervencaoNecessaria,
                    'resultado' => ResultadoEquipamento::ConfirmacaoDesconhecida,
                    'erro_sanitizado' => 'Processamento interrompido sem resposta conhecida do terminal.',
                    'lease_token' => null,
                    'lease_ate' => null,
                ])->save();

                return null;
            }

            $token = (string) Str::uuid7();
            $operacao->forceFill([
                'estado' => EstadoOutbox::Processando,
                'lease_token' => $token,
                'lease_ate' => $agora->copy()->addSeconds((int) config('integracoes.outbox.lease_segundos')),
                'tentativas' => $operacao->tentativas + 1,
            ])->save();

            return $token;
        });
    }

    // ---- Validações antes de tocar o equipamento ---------------------------

    private function bloqueioAntesDoEnvio(Operacao $operacao, Equipamento $equipamento): ?ResultadoOperacao
    {
        $tipo = $operacao->operacao;
        $diagnostico = in_array($tipo, [OperacaoIntegracao::TestarConexao, OperacaoIntegracao::ConsultarCapacidades], true);
        $testeBancada = $tipo === OperacaoIntegracao::AberturaRemota && $operacao->origem === ComandosAbertura::ORIGEM_TESTE_BANCADA;

        if ($equipamento->status === StatusEquipamento::Inativo) {
            return new ResultadoOperacao(ResultadoEquipamento::Recusado, 'equipamento_inativo', 'Equipamento inativo.');
        }
        if ($testeBancada) {
            if ($equipamento->status !== StatusEquipamento::EmHomologacao) {
                return new ResultadoOperacao(ResultadoEquipamento::Recusado, 'equipamento_fora_de_homologacao', 'O teste do relé só é feito com o terminal em homologação.');
            }
            if (! config('integracoes.teste_rele_habilitado')) {
                return new ResultadoOperacao(ResultadoEquipamento::Recusado, 'teste_rele_desabilitado', 'Teste do relé em bancada desabilitado nesta instalação.');
            }
        } elseif (! $diagnostico && $equipamento->status !== StatusEquipamento::Ativo) {
            return new ResultadoOperacao(ResultadoEquipamento::Recusado, 'equipamento_nao_ativo', 'O terminal ainda não está ativo.');
        }
        if (! $this->adaptadores->permitido($equipamento->adaptador)) {
            return new ResultadoOperacao(ResultadoEquipamento::Recusado, 'adaptador_indisponivel', 'Adaptador não permitido neste ambiente.');
        }
        if ($operacao->expira_em !== null && $operacao->expira_em < now()) {
            return new ResultadoOperacao(ResultadoEquipamento::Expirado, 'expirado', 'A operação expirou antes do envio.');
        }
        if ($tipo === OperacaoIntegracao::AberturaRemota && ! $testeBancada && ! config('integracoes.abertura_remota_habilitada')) {
            return new ResultadoOperacao(ResultadoEquipamento::Recusado, 'abertura_remota_desabilitada', 'Abertura remota desabilitada até a homologação em bancada.');
        }
        if ($tipo === OperacaoIntegracao::SincronizarCredencial && ($operacao->payload['tipo'] ?? null) === 'face') {
            return new ResultadoOperacao(ResultadoEquipamento::Recusado, 'biometria_bloqueada_adr013', 'Credencial facial bloqueada até a aprovação do ADR-013.');
        }

        $capacidade = $tipo->capacidadeExigida();
        if ($capacidade !== null && ! $equipamento->suporta($capacidade)) {
            return ResultadoOperacao::capacidadeAusente($capacidade, 'não verificada para este terminal');
        }

        return null;
    }

    // ---- Chamada à porta ---------------------------------------------------

    private function executar(Operacao $operacao, Equipamento $equipamento): ResultadoOperacao|CapacidadesDeclaradas|ResultadoColetaEventos
    {
        $porta = $this->adaptadores->para($equipamento->adaptador);
        $contexto = $this->contextos->para($equipamento, $operacao->correlation_id);
        $p = $operacao->payload;

        try {
            return match ($operacao->operacao) {
                OperacaoIntegracao::TestarConexao => $porta->testarConexao($contexto),
                OperacaoIntegracao::ConsultarCapacidades => $porta->consultarCapacidades($contexto),
                OperacaoIntegracao::SincronizarCredencial => $porta->sincronizarCredencial($contexto, new CredencialParaSincronizar(
                    credencialId: $p['credencial_id'],
                    tipo: $p['tipo'],
                    titularReferencia: $p['titular_referencia'],
                    nomeExibicao: $p['nome_exibicao'],
                    vigenciaInicio: new DateTimeImmutable($p['vigencia_inicio']),
                    vigenciaFim: isset($p['vigencia_fim']) ? new DateTimeImmutable($p['vigencia_fim']) : null,
                    direcao: Direcao::from($p['direcao']),
                    idExternoAtual: $this->idExterno($equipamento, $p['credencial_id']),
                )),
                OperacaoIntegracao::RevogarCredencial => $porta->revogarCredencial($contexto, $p['credencial_id'], $this->idExterno($equipamento, $p['credencial_id'])),
                OperacaoIntegracao::ConsultarSincronizacao => $porta->consultarSincronizacao($contexto, $p['credencial_id'], $this->idExterno($equipamento, $p['credencial_id'])),
                OperacaoIntegracao::ColetarEventos => $porta->coletarEventos($contexto, $p['cursor'] ?? null),
                OperacaoIntegracao::AberturaRemota => $porta->solicitarAbertura($contexto, new ComandoAbertura(
                    comandoId: $operacao->id,
                    pontoAcessoId: $p['ponto_acesso_id'],
                    decisaoReferencia: $p['decisao_referencia'] ?? (string) $operacao->agregado_id,
                    chaveIdempotencia: $operacao->chave_idempotencia,
                    solicitadoEm: DateTimeImmutable::createFromInterface($operacao->created_at),
                    expiraEm: DateTimeImmutable::createFromInterface($operacao->expira_em),
                    origem: $operacao->origem,
                    atorId: $operacao->ator_id,
                )),
                OperacaoIntegracao::ConsultarResultadoComando => $porta->consultarResultadoComando($contexto, $p['comando_id']),
            };
        } catch (Throwable $e) {
            $mensagem = $this->sanitizador->texto($e->getMessage(), $this->contextos->segredosConhecidos($equipamento));
            Log::warning('integracao.excecao_adaptador', [
                'operacao_id' => $operacao->id,
                'equipamento_id' => $equipamento->id,
                'operacao' => $operacao->operacao->value,
                'erro' => $mensagem,
            ]);

            // Numa abertura não se sabe se o terminal recebeu antes da falha.
            return $operacao->operacao === OperacaoIntegracao::AberturaRemota
                ? ResultadoOperacao::desconhecido('excecao_adaptador', (string) $mensagem)
                : new ResultadoOperacao(ResultadoEquipamento::FalhaTecnica, 'excecao_adaptador', $mensagem);
        }
    }

    // ---- Efeitos no domínio ------------------------------------------------

    private function aplicarEfeitos(Operacao $operacao, Equipamento $equipamento, ResultadoOperacao|CapacidadesDeclaradas|ResultadoColetaEventos $resultado, bool $contatouEquipamento): void
    {
        $equipamento = Equipamento::query()->lockForUpdate()->findOrFail($equipamento->id);
        $estado = $resultado->resultado;

        // Última comunicação só avança com resposta real do terminal.
        $respondeu = in_array($estado, [ResultadoEquipamento::Confirmado, ResultadoEquipamento::Aceito, ResultadoEquipamento::Recusado], true)
            && (! $resultado instanceof CapacidadesDeclaradas || $resultado->consultadoNoEquipamento);
        if ($contatouEquipamento && $respondeu) {
            $equipamento->ultima_comunicacao_at = now();
        }

        match ($operacao->operacao) {
            OperacaoIntegracao::TestarConexao => $this->efeitoTesteConexao($equipamento, $resultado),
            OperacaoIntegracao::ConsultarCapacidades => $this->efeitoCapacidades($equipamento, $resultado),
            OperacaoIntegracao::SincronizarCredencial, OperacaoIntegracao::RevogarCredencial,
            OperacaoIntegracao::ConsultarSincronizacao => $this->efeitoSincronizacao($operacao, $equipamento, $resultado),
            OperacaoIntegracao::ColetarEventos => $this->efeitoEventos($operacao, $equipamento, $resultado),
            OperacaoIntegracao::ConsultarResultadoComando => $this->efeitoReconciliacaoComando($operacao, $resultado),
            OperacaoIntegracao::AberturaRemota => null,
        };

        if (in_array($estado, [ResultadoEquipamento::FalhaTecnica, ResultadoEquipamento::Indisponivel, ResultadoEquipamento::ConfirmacaoDesconhecida], true)) {
            $equipamento->ultimo_erro_sanitizado = $this->mensagem($resultado);
        }
        if ($contatouEquipamento && ($estado->naoAlcancouEquipamento() || in_array($estado, [ResultadoEquipamento::FalhaTecnica, ResultadoEquipamento::ConfirmacaoDesconhecida], true)
            || ($resultado instanceof ResultadoOperacao && $resultado->codigo === 'autenticacao_recusada'))) {
            $equipamento->ultima_falha_at = now();
        }

        $equipamento->save();
    }

    private function efeitoTesteConexao(Equipamento $equipamento, ResultadoOperacao $resultado): void
    {
        $equipamento->ultimo_teste_at = now();
        $equipamento->estado_saude = match (true) {
            $resultado->resultado === ResultadoEquipamento::Confirmado => EstadoSaude::Conectado,
            $resultado->codigo === 'autenticacao_recusada' => EstadoSaude::CredencialInvalida,
            $resultado->resultado === ResultadoEquipamento::Indisponivel => EstadoSaude::Indisponivel,
            in_array($resultado->resultado, [ResultadoEquipamento::ConfirmacaoDesconhecida, ResultadoEquipamento::FalhaTecnica], true) => EstadoSaude::Degradado,
            default => $equipamento->estado_saude,
        };

        if ($resultado->resultado === ResultadoEquipamento::Confirmado) {
            $equipamento->ultimo_erro_sanitizado = null;
            $this->registrarInventarioDetectado($equipamento, $resultado);
        } elseif ($resultado->resultado !== ResultadoEquipamento::Indisponivel) {
            $equipamento->ultimo_erro_sanitizado = $this->mensagem($resultado);
        }
    }

    /**
     * O terminal informa firmware e série no teste de conexão. Campo vazio no
     * inventário é preenchido (auditado); divergência nunca sobrescreve o
     * inventário, só vira alerta (CA-ADR-016-008).
     */
    private function registrarInventarioDetectado(Equipamento $equipamento, ResultadoOperacao $resultado): void
    {
        $detectados = array_filter([
            'firmware_versao' => $resultado->dados['firmware'] ?? null,
            'numero_serie' => $resultado->dados['numero_serie'] ?? null,
        ], fn ($valor) => is_string($valor) && $valor !== '');

        $preenchidos = [];
        $divergencias = [];
        foreach ($detectados as $campo => $valor) {
            $atual = $equipamento->getAttribute($campo);
            if ($atual === null || $atual === '') {
                $equipamento->setAttribute($campo, $valor);
                $preenchidos[$campo] = ['old' => null, 'new' => $valor];
            } elseif ($atual !== $valor) {
                $divergencias[] = $campo === 'firmware_versao'
                    ? "firmware informado pelo terminal ({$valor}) diverge do inventário"
                    : "número de série informado pelo terminal ({$valor}) diverge do inventário";
            }
        }

        if ($divergencias !== []) {
            $equipamento->ultimo_erro_sanitizado = $this->sanitizador->texto(ucfirst(implode('; ', $divergencias)).'.');
        }

        if ($preenchidos !== []) {
            if (isset($preenchidos['firmware_versao'])) {
                $equipamento->capacidades()->update(['suportada' => false, 'estado_homologacao' => null, 'motivo_ausencia' => 'firmware alterado; verificar novamente']);
            }
            $equipamento->versao++;
            $this->audit->record(
                action: 'equipamento_inventario_detectado',
                module: 'integracoes',
                entityType: 'equipamentos',
                entityId: $equipamento->id,
                changes: $preenchidos,
                implantacaoId: $equipamento->implantacao_id,
            );
        }
    }

    private function efeitoCapacidades(Equipamento $equipamento, ResultadoOperacao|CapacidadesDeclaradas $resultado): void
    {
        if (! $resultado instanceof CapacidadesDeclaradas || $resultado->resultado !== ResultadoEquipamento::Confirmado) {
            return;
        }

        foreach (Capacidade::cases() as $capacidade) {
            EquipamentoCapacidade::query()->updateOrCreate(
                ['equipamento_id' => $equipamento->id, 'capacidade' => $capacidade->value],
                [
                    'suportada' => $resultado->suporta($capacidade),
                    'estado_homologacao' => $resultado->estado($capacidade),
                    'origem' => $resultado->consultadoNoEquipamento ? 'consultada_equipamento' : 'declarada_adaptador',
                    'versao_contrato' => $resultado->versaoContrato,
                    'firmware_versao' => $resultado->firmwareVersao,
                    'motivo_ausencia' => $resultado->suporta($capacidade) ? null : ($resultado->motivosAusencia[$capacidade->value] ?? 'não declarada'),
                    'verificada_em' => now(),
                ],
            );
        }

        if ($resultado->firmwareVersao !== null && $equipamento->firmware_versao !== null && $resultado->firmwareVersao !== $equipamento->firmware_versao) {
            $equipamento->ultimo_erro_sanitizado = "Firmware informado pelo terminal ({$resultado->firmwareVersao}) diverge do inventário.";
        }
    }

    private function efeitoSincronizacao(Operacao $operacao, Equipamento $equipamento, ResultadoOperacao $resultado): void
    {
        $credencialId = $operacao->payload['credencial_id'];
        $sincronizacao = CredencialSincronizacao::query()->lockForUpdate()->firstOrCreate(
            ['equipamento_id' => $equipamento->id, 'credencial_id' => $credencialId],
            ['estado' => EstadoSincronizacao::NaoEnviado],
        );

        $vaiRepetir = $this->vaiRepetir($operacao, $resultado);
        $novoEstado = match ($operacao->operacao) {
            OperacaoIntegracao::SincronizarCredencial => $this->estadoAposEnvio($resultado, $vaiRepetir, EstadoSincronizacao::Sincronizado),
            OperacaoIntegracao::RevogarCredencial => $this->estadoAposEnvio($resultado, $vaiRepetir, EstadoSincronizacao::Removido),
            OperacaoIntegracao::ConsultarSincronizacao => $this->estadoAposConsulta($operacao, $resultado, $sincronizacao->estado),
        };

        $dados = [
            'estado' => $novoEstado,
            'ultima_operacao_id' => $operacao->id,
            'ultima_tentativa_em' => now(),
            'erro_sanitizado' => $resultado->resultado === ResultadoEquipamento::Confirmado ? null : $this->mensagem($resultado),
            'versao' => $sincronizacao->versao + 1,
        ];
        if ($novoEstado === EstadoSincronizacao::Sincronizado) {
            $dados['sincronizado_em'] = now();
            $dados['removido_em'] = null;
        }
        if ($novoEstado === EstadoSincronizacao::Removido) {
            $dados['removido_em'] = now();
        }
        $sincronizacao->forceFill($dados)->save();

        if ($operacao->operacao === OperacaoIntegracao::SincronizarCredencial && $resultado->resultado === ResultadoEquipamento::Confirmado && $resultado->idExterno !== null) {
            $this->registrarReferenciaExterna($equipamento, $credencialId, $resultado->idExterno);
        }
        if ($novoEstado === EstadoSincronizacao::Removido) {
            ReferenciaExterna::query()
                ->where('equipamento_id', $equipamento->id)
                ->where('entidade_id', $credencialId)
                ->whereNull('substituida_em')
                ->update(['substituida_em' => now()]);
        }
    }

    private function estadoAposEnvio(ResultadoOperacao $resultado, bool $vaiRepetir, EstadoSincronizacao $sucesso): EstadoSincronizacao
    {
        return match ($resultado->resultado) {
            ResultadoEquipamento::Confirmado => $sucesso,
            ResultadoEquipamento::Aceito, ResultadoEquipamento::Enviado => EstadoSincronizacao::Enviado,
            // Desconhecido: não reenviar às cegas; reconciliar por consulta.
            ResultadoEquipamento::ConfirmacaoDesconhecida => EstadoSincronizacao::AtualizacaoPendente,
            ResultadoEquipamento::Indisponivel, ResultadoEquipamento::FalhaTecnica => $vaiRepetir ? EstadoSincronizacao::Aguardando : EstadoSincronizacao::IntervencaoNecessaria,
            default => EstadoSincronizacao::Falha,
        };
    }

    private function estadoAposConsulta(Operacao $operacao, ResultadoOperacao $resultado, EstadoSincronizacao $atual): EstadoSincronizacao
    {
        if ($resultado->resultado !== ResultadoEquipamento::Confirmado || ! array_key_exists('presente', $resultado->dados)) {
            return $resultado->resultado === ResultadoEquipamento::CapacidadeAusente ? EstadoSincronizacao::IntervencaoNecessaria : $atual;
        }

        $presente = (bool) $resultado->dados['presente'];
        $esperado = $operacao->payload['esperado'] ?? 'presente';

        return match (true) {
            $esperado === 'presente' && $presente => EstadoSincronizacao::Sincronizado,
            $esperado === 'ausente' && ! $presente => EstadoSincronizacao::Removido,
            // Divergência comprovada: o estado do terminal difere do SDV.
            default => EstadoSincronizacao::IntervencaoNecessaria,
        };
    }

    private function efeitoEventos(Operacao $operacao, Equipamento $equipamento, ResultadoColetaEventos|ResultadoOperacao $resultado): void
    {
        if (! $resultado instanceof ResultadoColetaEventos || $resultado->resultado !== ResultadoEquipamento::Confirmado) {
            return;
        }

        [$novos, $duplicados] = $this->coletor->registrar($equipamento, $resultado->eventos, $operacao->correlation_id);

        $operacao->resultado_dados = [
            'cursor' => $resultado->cursor,
            'novos' => $novos,
            'duplicados' => $duplicados,
            'invalidos' => $resultado->descartadosInvalidos,
        ];
    }

    private function efeitoReconciliacaoComando(Operacao $consulta, ResultadoOperacao $resultado): void
    {
        $comando = Operacao::query()->lockForUpdate()->find($consulta->payload['comando_id']);
        if ($comando === null || $comando->resultado !== ResultadoEquipamento::ConfirmacaoDesconhecida) {
            return;
        }

        $executado = $resultado->dados['executado'] ?? null;
        $novo = match (true) {
            $resultado->resultado === ResultadoEquipamento::Confirmado && $executado === true => ResultadoEquipamento::Confirmado,
            $resultado->resultado === ResultadoEquipamento::Recusado && $executado === false => ResultadoEquipamento::Recusado,
            default => null,
        };

        if ($novo === null) {
            $comando->forceFill(['estado' => EstadoOutbox::IntervencaoNecessaria])->save();

            return;
        }

        $comando->forceFill([
            'resultado' => $novo,
            'estado' => EstadoOutbox::Processado,
            'resultado_dados' => array_merge($comando->resultado_dados ?? [], ['reconciliado_por' => $consulta->id]),
        ])->save();
    }

    // ---- Conclusão ---------------------------------------------------------

    private function concluir(Operacao $operacao, ResultadoOperacao|CapacidadesDeclaradas|ResultadoColetaEventos $resultado): void
    {
        $estado = $resultado->resultado;
        $vaiRepetir = $this->vaiRepetir($operacao, $resultado);

        $estadoOutbox = match (true) {
            $vaiRepetir => EstadoOutbox::FalhaTemporaria,
            // Diagnóstico manual de tentativa única: a falha é o próprio resultado do teste.
            in_array($estado, [ResultadoEquipamento::FalhaTecnica, ResultadoEquipamento::Indisponivel], true)
                && $operacao->operacao->permiteRetentativaAutomatica()
                && ! ($operacao->origem === 'manual' && $operacao->max_tentativas === 1) => EstadoOutbox::IntervencaoNecessaria,
            $estado === ResultadoEquipamento::ConfirmacaoDesconhecida
                && $operacao->operacao === OperacaoIntegracao::AberturaRemota
                && ! $operacao->equipamento->suporta(Capacidade::ConsultarResultadoComando) => EstadoOutbox::IntervencaoNecessaria,
            default => EstadoOutbox::Processado,
        };

        $operacao->forceFill([
            'estado' => $estadoOutbox,
            'resultado' => $estado,
            'erro_sanitizado' => $estado === ResultadoEquipamento::Confirmado ? null : $this->mensagem($resultado),
            'resultado_dados' => $operacao->resultado_dados ?? $this->dadosSeguros($resultado),
            'processado_em' => $vaiRepetir ? null : now(),
            'disponivel_em' => $vaiRepetir ? now()->addSeconds($this->backoff($operacao->tentativas)) : $operacao->disponivel_em,
            'lease_token' => null,
            'lease_ate' => null,
        ])->save();
    }

    private function vaiRepetir(Operacao $operacao, ResultadoOperacao|CapacidadesDeclaradas|ResultadoColetaEventos $resultado): bool
    {
        return $operacao->operacao->permiteRetentativaAutomatica()
            && in_array($resultado->resultado, [ResultadoEquipamento::FalhaTecnica, ResultadoEquipamento::Indisponivel], true)
            && $this->codigo($resultado) !== 'resposta_invalida'
            && $operacao->tentativas < $operacao->max_tentativas;
    }

    private function backoff(int $tentativa): int
    {
        $base = (int) config('integracoes.outbox.backoff_base_segundos');
        $maximo = (int) config('integracoes.outbox.backoff_maximo_segundos');
        $atraso = min($maximo, $base * (2 ** max(0, $tentativa - 1)));

        return $atraso + random_int(0, max(1, intdiv($atraso, 5)));
    }

    private function registrarOcorrencia(Operacao $operacao, ResultadoOperacao|CapacidadesDeclaradas|ResultadoColetaEventos $resultado, int $latencia): void
    {
        IntegracaoOcorrencia::query()->create([
            'equipamento_id' => $operacao->equipamento_id,
            'operacao_integracao_id' => $operacao->id,
            'tipo' => 'tentativa',
            'resultado' => $resultado->resultado->value,
            'codigo' => $this->codigo($resultado),
            'mensagem_sanitizada' => $this->mensagem($resultado),
            'latencia_ms' => $latencia,
            'correlation_id' => $operacao->correlation_id,
            'ocorrido_em' => now(),
        ]);

        Log::info('integracao.operacao', $this->sanitizador->dados([
            'operacao_id' => $operacao->id,
            'implantacao_id' => $operacao->implantacao_id,
            'equipamento_id' => $operacao->equipamento_id,
            'operacao' => $operacao->operacao->value,
            'tentativa' => $operacao->tentativas,
            'resultado' => $resultado->resultado->value,
            'codigo' => $this->codigo($resultado),
            'latencia_ms' => $latencia,
            'correlation_id' => $operacao->correlation_id,
        ]));
    }

    private function auditar(Operacao $operacao, ResultadoOperacao|CapacidadesDeclaradas|ResultadoColetaEventos $resultado): void
    {
        $this->audit->record(
            action: 'integracao_'.$operacao->operacao->value,
            module: 'integracoes',
            entityType: 'equipamentos',
            entityId: $operacao->equipamento_id,
            result: match ($resultado->resultado) {
                ResultadoEquipamento::Confirmado => 'sucesso',
                // Aceito pelo terminal não é execução comprovada (RN-080).
                ResultadoEquipamento::Aceito => 'aceito',
                ResultadoEquipamento::ConfirmacaoDesconhecida => 'desconhecido',
                default => 'falha',
            },
            reasonCode: $this->codigo($resultado),
            metadata: [
                'operacao_id' => $operacao->id,
                'operacao' => $operacao->operacao->value,
                'resultado' => $resultado->resultado->value,
                'estado_outbox' => $operacao->estado->value,
                'tentativa' => $operacao->tentativas,
                'correlation_id' => $operacao->correlation_id,
            ],
            implantacaoId: $operacao->implantacao_id,
        );
    }

    // ---- Auxiliares --------------------------------------------------------

    private function registrarReferenciaExterna(Equipamento $equipamento, string $credencialId, string $idExterno): void
    {
        $vigente = ReferenciaExterna::query()
            ->where('equipamento_id', $equipamento->id)
            ->where('entidade_id', $credencialId)
            ->whereNull('substituida_em')
            ->first();

        if ($vigente?->id_externo === $idExterno) {
            return;
        }

        $vigente?->forceFill(['substituida_em' => now()])->save();

        ReferenciaExterna::query()->create([
            'adaptador' => $equipamento->adaptador,
            'equipamento_id' => $equipamento->id,
            'entidade_tipo' => 'credencial',
            'entidade_id' => $credencialId,
            'tipo_externo' => 'credencial_no_equipamento:'.$equipamento->id,
            'id_externo' => $idExterno,
        ]);
    }

    private function idExterno(Equipamento $equipamento, string $credencialId): ?string
    {
        return ReferenciaExterna::query()
            ->where('equipamento_id', $equipamento->id)
            ->where('entidade_id', $credencialId)
            ->whereNull('substituida_em')
            ->value('id_externo');
    }

    private function codigo(ResultadoOperacao|CapacidadesDeclaradas|ResultadoColetaEventos $resultado): ?string
    {
        return $resultado instanceof ResultadoOperacao ? $resultado->codigo : null;
    }

    private function mensagem(ResultadoOperacao|CapacidadesDeclaradas|ResultadoColetaEventos $resultado): ?string
    {
        return $this->sanitizador->texto($resultado->mensagem);
    }

    /** @return array<string, mixed>|null */
    private function dadosSeguros(ResultadoOperacao|CapacidadesDeclaradas|ResultadoColetaEventos $resultado): ?array
    {
        return match (true) {
            $resultado instanceof ResultadoOperacao => $this->sanitizador->dados($resultado->dados) ?: null,
            $resultado instanceof CapacidadesDeclaradas => [
                'suportadas' => array_map(fn (Capacidade $c) => $c->value, $resultado->suportadas),
                'firmware' => $resultado->firmwareVersao,
            ],
            default => null,
        };
    }
}
