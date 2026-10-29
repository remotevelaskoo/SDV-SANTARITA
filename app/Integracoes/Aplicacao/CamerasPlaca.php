<?php

namespace App\Integracoes\Aplicacao;

use App\Integracoes\Dominio\Contratos\PortaCameraSnapshot;
use App\Integracoes\Dominio\Dados\ContextoCamera;
use App\Integracoes\Dominio\Dados\ImagemCapturada;
use App\Integracoes\Dominio\Enums\Direcao;
use App\Integracoes\Dominio\Enums\EstadoSaude;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Integracoes\Dominio\Enums\StatusPontoAcesso;
use App\Integracoes\Dominio\Enums\TipoEquipamento;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Integracoes\Infra\ResolvedorSegredo;
use App\Integracoes\Infra\SanitizadorIntegracao;
use App\Models\CameraPontoVinculo;
use App\Models\CapturaPlaca;
use App\Models\Equipamento;
use App\Models\IntegracaoOcorrencia;
use App\Models\PontoAcesso;
use App\Models\Posto;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PrivateFileService;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Câmeras IP de placas (AJ-006, fatia 5).
 *
 * - a câmera é um equipamento com finalidade "placas", ligada a um ponto;
 * - o navegador conhece só o identificador interno: endereço, porta,
 *   caminho e credencial são resolvidos aqui e entregues ao adaptador;
 * - a credencial é sempre referência (env:NOME), nunca a senha;
 * - captura estática sob demanda; cada imagem vira evidência privada e
 *   auditada, sem a imagem em log;
 * - a leitura da placa é outra etapa: a captura não preenche nem decide nada.
 */
class CamerasPlaca
{
    public const FINALIDADE = 'placas';

    public function __construct(
        private Container $container,
        private CadastroEquipamentos $cadastro,
        private ResolvedorSegredo $segredos,
        private SanitizadorIntegracao $sanitizador,
        private PrivateFileService $arquivos,
        private TurnosOperacionais $turnos,
        private AuditService $audit,
    ) {}

    /** @return list<string> */
    public function adaptadoresPermitidos(): array
    {
        return array_values(array_filter(array_keys((array) config('integracoes.cameras_placa.adaptadores')), fn (string $codigo) => $this->adaptadorPermitido($codigo)));
    }

    /**
     * @param  array{nome: string, fabricante?: ?string, modelo?: ?string, endereco_rede?: ?string, porta_rede?: ?int,
     *     esquema?: string, caminho_snapshot?: ?string, adaptador: string, timeout_segundos?: int}  $dados
     */
    public function cadastrar(array $dados, User $ator): Equipamento
    {
        $this->exigirAdministracao($ator);

        $nome = trim((string) ($dados['nome'] ?? ''));
        if ($nome === '' || mb_strlen($nome) > 120) {
            throw new RegraIntegracaoViolada('nome_invalido', 'Informe o nome da câmera (até 120 caracteres).');
        }

        $adaptador = (string) ($dados['adaptador'] ?? '');
        if (! $this->adaptadorPermitido($adaptador)) {
            throw new RegraIntegracaoViolada('adaptador_indisponivel', 'Tipo de conexão inexistente ou não permitido neste ambiente.');
        }

        $simulador = $this->ehSimulador($adaptador);
        $endereco = $simulador ? '127.0.0.1' : trim((string) ($dados['endereco_rede'] ?? ''));
        $porta = $simulador ? null : ($dados['porta_rede'] ?? null);
        $caminho = $simulador ? '/simulador' : trim((string) ($dados['caminho_snapshot'] ?? ''));
        $esquema = $simulador ? 'http' : (string) ($dados['esquema'] ?? 'http');
        $timeout = (int) ($dados['timeout_segundos'] ?? 5);

        if (! $simulador) {
            $this->validarRede($endereco, $porta);
            if (! preg_match('#^/[A-Za-z0-9._~/?&=%:+-]{0,250}$#', $caminho)) {
                throw new RegraIntegracaoViolada('caminho_invalido', 'Informe o caminho do snapshot começando com "/", sem endereço nem senha (ex.: /snapshot.jpg).');
            }
            if (preg_match('#(user|usuario|pass|senha|pwd|token|auth)=#i', $caminho)) {
                throw new RegraIntegracaoViolada('caminho_com_credencial', 'O caminho não pode conter usuário, senha ou token. Cadastre a credencial pela referência.');
            }
        }
        if (! in_array($esquema, ['http', 'https'], true)) {
            throw new RegraIntegracaoViolada('esquema_invalido', 'Informe HTTP ou HTTPS.');
        }
        if ($timeout < 1 || $timeout > 30) {
            throw new RegraIntegracaoViolada('timeout_invalido', 'O tempo limite deve ficar entre 1 e 30 segundos.');
        }

        return DB::transaction(function () use ($dados, $nome, $adaptador, $simulador, $endereco, $porta, $caminho, $esquema, $timeout, $ator): Equipamento {
            $camera = Equipamento::query()->create([
                'nome' => $nome,
                'tipo' => TipoEquipamento::CameraPlacas,
                'finalidade' => self::FINALIDADE,
                'fabricante' => $simulador ? 'Simulador' : (trim((string) ($dados['fabricante'] ?? '')) ?: 'Não informado'),
                'modelo' => $simulador ? 'Câmera simulada' : (trim((string) ($dados['modelo'] ?? '')) ?: 'Não informado'),
                'endereco_rede' => $endereco,
                'porta_rede' => $porta,
                'esquema' => $esquema,
                'protocolo' => $simulador ? 'simulador' : 'snapshot_http',
                'caminho_snapshot' => $caminho,
                'adaptador' => $adaptador,
                'direcao' => Direcao::Bidirecional,
                'timeout_segundos' => $timeout,
                'status' => StatusEquipamento::Configurando,
                'estado_saude' => EstadoSaude::Desconhecido,
                'created_by' => $ator->id,
                'updated_by' => $ator->id,
            ]);

            $this->audit->record(
                action: 'camera_placa_cadastrada',
                module: 'integracoes',
                entityType: 'equipamentos',
                entityId: $camera->id,
                changes: ['nome' => ['old' => null, 'new' => $nome], 'adaptador' => ['old' => null, 'new' => $adaptador]],
                metadata: ['simulador' => $simulador, 'esquema' => $esquema],
            );

            return $camera;
        });
    }

    /** A senha nunca passa por aqui: só a referência env:NOME (ADR-009). */
    public function definirCredencial(Equipamento $camera, string $referencia, ?string $usuario, User $ator): void
    {
        $this->exigirAdministracao($ator);
        $camera = $this->camera($camera->id);
        if (! str_starts_with($referencia, 'env:')) {
            throw new RegraIntegracaoViolada('referencia_segredo_invalida', 'Informe somente a referência do segredo, no formato env:NOME_DA_VARIAVEL.');
        }
        if ($usuario !== null && $usuario !== '' && ! preg_match('/^[A-Za-z0-9._@-]{1,32}$/', $usuario)) {
            throw new RegraIntegracaoViolada('usuario_tecnico_invalido', 'Usuário técnico inválido (até 32 letras, números, ponto, hífen ou sublinhado).');
        }

        $this->cadastro->definirCredencialTecnica($camera, $referencia, $usuario ?: null, $ator);
    }

    public function vincularAoPonto(Equipamento $camera, PontoAcesso $ponto, User $ator): CameraPontoVinculo
    {
        $this->exigirAdministracao($ator);

        return DB::transaction(function () use ($camera, $ponto, $ator): CameraPontoVinculo {
            $camera = Equipamento::query()->camerasPlaca()->lockForUpdate()->findOrFail($camera->id);
            $ponto = PontoAcesso::query()->findOrFail($ponto->id);

            if ($camera->status === StatusEquipamento::Inativo) {
                throw new RegraIntegracaoViolada('camera_inativa', 'Câmera inativa não é vinculada; cadastre uma nova.');
            }
            if ($ponto->status === StatusPontoAcesso::Inativo) {
                throw new RegraIntegracaoViolada('ponto_inativo', 'Ponto de acesso inativo não recebe câmera.');
            }

            $anterior = $camera->vinculoCameraVigente;
            if ($anterior?->ponto_acesso_id === $ponto->id) {
                return $anterior;
            }
            $anterior?->forceFill(['ended_at' => now(), 'ended_by' => $ator->id, 'motivo_encerramento' => 'câmera movida para outro ponto'])->save();

            $vinculo = CameraPontoVinculo::query()->create([
                'equipamento_id' => $camera->id,
                'ponto_acesso_id' => $ponto->id,
                'started_at' => now(),
                'created_by' => $ator->id,
            ]);
            $camera->forceFill(['status' => StatusEquipamento::Ativo, 'updated_by' => $ator->id, 'versao' => $camera->versao + 1])->save();

            $this->audit->record(
                action: 'camera_placa_vinculada_ao_ponto',
                module: 'integracoes',
                entityType: 'equipamentos',
                entityId: $camera->id,
                changes: ['ponto_acesso_id' => ['old' => $anterior?->ponto_acesso_id, 'new' => $ponto->id]],
                metadata: ['ponto_codigo' => $ponto->codigo],
            );

            return $vinculo;
        });
    }

    /** Inativar encerra o vínculo; capturas já feitas continuam como evidência. */
    public function inativar(Equipamento $camera, string $motivo, User $ator): void
    {
        $this->exigirAdministracao($ator);
        if (mb_strlen(trim($motivo)) < 5) {
            throw new RegraIntegracaoViolada('motivo_obrigatorio', 'Informe o motivo (pelo menos 5 caracteres).');
        }

        DB::transaction(function () use ($camera, $motivo, $ator): void {
            $camera = Equipamento::query()->camerasPlaca()->lockForUpdate()->findOrFail($camera->id);
            $camera->vinculoCameraVigente?->forceFill(['ended_at' => now(), 'ended_by' => $ator->id, 'motivo_encerramento' => $motivo])->save();
            $camera->forceFill(['status' => StatusEquipamento::Inativo, 'inactivated_at' => now(), 'updated_by' => $ator->id, 'versao' => $camera->versao + 1])->save();

            $this->audit->record(
                action: 'camera_placa_inativada',
                module: 'integracoes',
                entityType: 'equipamentos',
                entityId: $camera->id,
                justification: $motivo,
            );
        });
    }

    /**
     * Câmeras que o operador pode usar agora: as dos pontos do posto assumido
     * no turno; sem turno, as câmeras ativas da implantação.
     *
     * @return Collection<int, array{id: string, nome: string, ponto: string, ponto_id: string, simulador: bool}>
     */
    public function disponiveisPara(User $operador): Collection
    {
        $pontosDoPosto = null;
        $turno = $this->turnos->turnoAtivo($operador);
        if ($turno !== null) {
            $pontosDoPosto = Posto::query()->find($turno->posto_id)?->pontosVigentes()->pluck('pontos_acesso.id')->all() ?? [];
        }

        return Equipamento::query()
            ->camerasPlaca()
            ->where('status', StatusEquipamento::Ativo->value)
            ->with('vinculoCameraVigente.pontoAcesso')
            ->orderBy('nome')
            ->get()
            ->filter(fn (Equipamento $camera) => $camera->vinculoCameraVigente !== null
                && $this->adaptadorPermitido($camera->adaptador)
                && ($pontosDoPosto === null || in_array($camera->vinculoCameraVigente->ponto_acesso_id, $pontosDoPosto, true)))
            ->map(fn (Equipamento $camera) => [
                'id' => $camera->id,
                'nome' => $camera->nome,
                'ponto' => $camera->vinculoCameraVigente->pontoAcesso->nome,
                'ponto_id' => $camera->vinculoCameraVigente->ponto_acesso_id,
                'simulador' => $this->ehSimulador($camera->adaptador),
            ])
            ->values();
    }

    /**
     * Captura uma imagem estática e guarda como evidência.
     *
     * @return array{ok: true, captura: CapturaPlaca}|array{ok: false, codigo: string, mensagem: string}
     */
    public function capturar(string $cameraId, User $operador): array
    {
        if (! $operador->hasPermission('validacao.registrar') && ! $operador->hasPermission('integracoes.gerenciar')) {
            throw new RegraIntegracaoViolada('sem_permissao', 'Você não tem permissão para capturar a placa.');
        }

        $camera = $this->camera($cameraId);
        $vinculo = $camera->vinculoCameraVigente;
        if ($camera->status !== StatusEquipamento::Ativo || $vinculo === null) {
            throw new RegraIntegracaoViolada('camera_indisponivel', 'Esta câmera não está ativa em um ponto de acesso.');
        }
        if (! $this->adaptadorPermitido($camera->adaptador)) {
            throw new RegraIntegracaoViolada('adaptador_indisponivel', 'O tipo de conexão desta câmera não é permitido neste ambiente.');
        }

        $intervalo = max(1, (int) config('integracoes.cameras_placa.intervalo_minimo_segundos', 2));
        if (! Cache::add('integracoes:camera-placa:'.$camera->id, 1, $intervalo)) {
            throw new RegraIntegracaoViolada('captura_em_andamento', 'Aguarde um instante: esta câmera acabou de capturar.');
        }

        $porta = $this->adaptador($camera->adaptador);
        $inicio = hrtime(true);
        $imagem = $this->chamar($porta, $camera);
        $imagem = $this->validar($imagem);
        $latencia = (int) ((hrtime(true) - $inicio) / 1_000_000);

        if ($imagem->resultado !== ResultadoEquipamento::Confirmado) {
            $this->registrarFalha($camera, $imagem, $latencia);

            return ['ok' => false, 'codigo' => (string) $imagem->codigo, 'mensagem' => (string) $this->sanitizador->texto($imagem->mensagem)];
        }

        $arquivo = $this->arquivos->storeEvidenciaPlaca($camera->implantacao_id, (string) $imagem->conteudo(), 'camera_placa_'.$porta->origem());

        try {
            $captura = DB::transaction(function () use ($camera, $vinculo, $operador, $arquivo, $imagem, $porta, $latencia): CapturaPlaca {
                $captura = CapturaPlaca::query()->create([
                    'equipamento_id' => $camera->id,
                    'ponto_acesso_id' => $vinculo->ponto_acesso_id,
                    'posto_id' => $this->turnos->turnoAtivo($operador)?->posto_id,
                    'arquivo_id' => $arquivo->id,
                    'operador_id' => $operador->id,
                    'capturada_em' => $imagem->capturadaEm ?? now(),
                    'origem' => $porta->origem(),
                ]);

                $camera->forceFill(['ultima_comunicacao_at' => now()])->save();
                IntegracaoOcorrencia::query()->create([
                    'equipamento_id' => $camera->id,
                    'tipo' => 'captura_placa',
                    'resultado' => ResultadoEquipamento::Confirmado->value,
                    'latencia_ms' => $latencia,
                    'ocorrido_em' => now(),
                ]);
                $this->audit->record(
                    action: 'placa_imagem_capturada',
                    module: 'validacao',
                    entityType: 'capturas_placa',
                    entityId: $captura->id,
                    classification: 'restrita',
                    metadata: [
                        'equipamento_id' => $camera->id,
                        'ponto_acesso_id' => $vinculo->ponto_acesso_id,
                        'origem' => $porta->origem(),
                        'tamanho_bytes' => $imagem->tamanhoBytes(),
                        'latencia_ms' => $latencia,
                    ],
                );

                return $captura;
            });
        } catch (Throwable $e) {
            Storage::disk($arquivo->disk)->delete($arquivo->object_key);
            $arquivo->forceFill(['status' => 'falha', 'error_message' => 'captura_nao_registrada'])->save();

            throw $e;
        }

        return ['ok' => true, 'captura' => $captura];
    }

    public function ehSimulador(string $adaptador): bool
    {
        return str_starts_with($adaptador, 'simulador');
    }

    private function chamar(PortaCameraSnapshot $porta, Equipamento $camera): ImagemCapturada
    {
        $credencial = $camera->credencialTecnicaAtiva();

        try {
            return $porta->capturarSnapshot(new ContextoCamera(
                implantacaoId: $camera->implantacao_id,
                equipamentoId: $camera->id,
                esquema: $camera->esquema ?? 'http',
                enderecoRede: $camera->endereco_rede,
                portaRede: $camera->porta_rede,
                caminhoSnapshot: (string) $camera->caminho_snapshot,
                timeoutSegundos: $camera->timeout_segundos,
                usuarioTecnico: $credencial?->usuario_tecnico,
                obterSegredo: fn () => $credencial === null ? null : $this->segredos->resolverCredencial($credencial),
                correlationId: (string) Str::uuid7(),
                tlsPinSha256: $camera->tls_pin_sha256,
                tamanhoMaximoBytes: (int) config('integracoes.cameras_placa.tamanho_maximo_bytes'),
            ));
        } catch (Throwable $e) {
            Log::warning('integracao.excecao_adaptador', [
                'equipamento_id' => $camera->id,
                'operacao' => 'capturar_placa',
                'erro' => $this->sanitizador->texto($e->getMessage()),
            ]);

            return ImagemCapturada::falha(ResultadoEquipamento::FalhaTecnica, 'excecao_adaptador', 'Falha inesperada ao capturar a imagem.');
        }
    }

    private function validar(ImagemCapturada $imagem): ImagemCapturada
    {
        if ($imagem->resultado !== ResultadoEquipamento::Confirmado) {
            return $imagem;
        }

        $info = @getimagesizefromstring((string) $imagem->conteudo());

        return match (true) {
            $imagem->tamanhoBytes() === 0,
            $imagem->tamanhoBytes() > (int) config('integracoes.cameras_placa.tamanho_maximo_bytes') => ImagemCapturada::falha(ResultadoEquipamento::FalhaTecnica, 'imagem_tamanho_invalido', 'A imagem da câmera está vazia ou grande demais.'),
            ! is_array($info) || ! in_array($info['mime'] ?? null, ['image/jpeg', 'image/png'], true) => ImagemCapturada::falha(ResultadoEquipamento::FalhaTecnica, 'resposta_invalida', 'A câmera não devolveu uma imagem válida.'),
            default => $imagem,
        };
    }

    private function registrarFalha(Equipamento $camera, ImagemCapturada $imagem, int $latencia): void
    {
        $mensagem = $this->sanitizador->texto($imagem->mensagem);
        $camera->forceFill(['ultima_falha_at' => now(), 'ultimo_erro_sanitizado' => $mensagem])->save();

        IntegracaoOcorrencia::query()->create([
            'equipamento_id' => $camera->id,
            'tipo' => 'captura_placa',
            'resultado' => $imagem->resultado->value,
            'codigo' => $imagem->codigo,
            'mensagem_sanitizada' => $mensagem,
            'latencia_ms' => $latencia,
            'ocorrido_em' => now(),
        ]);
        $this->audit->record(
            action: 'placa_imagem_capturada',
            module: 'validacao',
            entityType: 'equipamentos',
            entityId: $camera->id,
            result: 'falha',
            reasonCode: $imagem->codigo,
            metadata: ['resultado' => $imagem->resultado->value, 'latencia_ms' => $latencia],
        );
    }

    private function camera(string $id): Equipamento
    {
        return Equipamento::query()->camerasPlaca()->with('vinculoCameraVigente.pontoAcesso')->findOrFail($id);
    }

    private function adaptadorPermitido(string $codigo): bool
    {
        return array_key_exists($codigo, (array) config('integracoes.cameras_placa.adaptadores'))
            && (! $this->ehSimulador($codigo) || (bool) config('integracoes.simulador_permitido'));
    }

    private function adaptador(string $codigo): PortaCameraSnapshot
    {
        $adaptador = $this->container->make(config("integracoes.cameras_placa.adaptadores.{$codigo}"));
        if (! $adaptador instanceof PortaCameraSnapshot) {
            throw new RegraIntegracaoViolada('adaptador_invalido', 'O adaptador da câmera não implementa a porta de snapshot.');
        }

        return $adaptador;
    }

    private function exigirAdministracao(User $ator): void
    {
        if (! $ator->hasPermission('integracoes.gerenciar')) {
            throw new RegraIntegracaoViolada('sem_permissao', 'Você não tem permissão para configurar câmeras.');
        }
    }

    private function validarRede(string $endereco, mixed $porta): void
    {
        if (filter_var($endereco, FILTER_VALIDATE_IP) === false) {
            throw new RegraIntegracaoViolada('endereco_invalido', 'Informe o IP fixo ou reservado da câmera ou do gateway de vídeo.');
        }
        if (filter_var($endereco, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
            throw new RegraIntegracaoViolada('endereco_publico', 'A câmera deve estar em rede local; endereço público não é aceito.');
        }
        if ($porta !== null && (! is_int($porta) || $porta < 1 || $porta > 65535)) {
            throw new RegraIntegracaoViolada('porta_invalida', 'Porta de rede deve estar entre 1 e 65535.');
        }
    }
}
