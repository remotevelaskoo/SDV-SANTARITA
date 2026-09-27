<?php

namespace App\Integracoes\Aplicacao;

use App\Integracoes\Dominio\Enums\Direcao;
use App\Integracoes\Dominio\Enums\EstadoSaude;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Integracoes\Dominio\Enums\StatusPontoAcesso;
use App\Integracoes\Dominio\Enums\TipoEquipamento;
use App\Integracoes\Dominio\Enums\TipoPontoAcesso;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Integracoes\Infra\RegistroAdaptadores;
use App\Integracoes\Infra\ResolvedorSegredo;
use App\Models\Equipamento;
use App\Models\EquipamentoCredencial;
use App\Models\EquipamentoPontoVinculo;
use App\Models\PontoAcesso;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Cadastro de pontos de acesso e equipamentos por implantação, vínculo
 * exclusivo terminal ↔ ponto e referência protegida à credencial técnica
 * (ADR-007, ADR-009, ADR-016). Toda operação é transacional e auditada;
 * nada é excluído fisicamente.
 */
class CadastroEquipamentos
{
    private const CAMPOS_INVENTARIO = [
        'nome', 'numero_serie', 'endereco_rede', 'porta_rede', 'firmware_versao', 'direcao', 'timeout_segundos',
    ];

    public function __construct(
        private AuditService $audit,
        private RegistroAdaptadores $adaptadores,
        private ResolvedorSegredo $segredos,
    ) {}

    /** @param  array{codigo: string, nome: string, tipo: string, direcao_suportada: string, localizacao?: ?string, observacao?: ?string}  $dados */
    public function registrarPontoAcesso(array $dados, ?User $ator = null): PontoAcesso
    {
        $tipo = TipoPontoAcesso::tryFrom($dados['tipo'] ?? '')
            ?? throw new RegraIntegracaoViolada('tipo_ponto_invalido', 'Neste recorte o ponto de acesso deve ser uma cancela ou uma catraca.');
        $direcao = Direcao::tryFrom($dados['direcao_suportada'] ?? '')
            ?? throw new RegraIntegracaoViolada('direcao_invalida', 'Direção suportada deve ser entrada, saída ou bidirecional.');

        return DB::transaction(function () use ($dados, $tipo, $direcao, $ator): PontoAcesso {
            if (PontoAcesso::query()->where('codigo', $dados['codigo'])->exists()) {
                throw new RegraIntegracaoViolada('codigo_ponto_duplicado', 'Já existe um ponto de acesso com este código nesta implantação.');
            }

            $ponto = PontoAcesso::query()->create([
                'codigo' => $dados['codigo'],
                'nome' => $dados['nome'],
                'tipo' => $tipo,
                'direcao_suportada' => $direcao,
                'localizacao' => $dados['localizacao'] ?? null,
                'observacao' => $dados['observacao'] ?? null,
                'status' => StatusPontoAcesso::EmImplantacao,
                'created_by' => $ator?->id,
                'updated_by' => $ator?->id,
            ]);

            $this->auditar('ponto_acesso_cadastrado', 'pontos_acesso', $ponto->id, [
                'codigo' => ['old' => null, 'new' => $ponto->codigo],
                'tipo' => ['old' => null, 'new' => $tipo->value],
                'direcao_suportada' => ['old' => null, 'new' => $direcao->value],
            ]);

            return $ponto;
        });
    }

    public function alterarStatusPonto(PontoAcesso $ponto, StatusPontoAcesso $novo, string $motivo, ?User $ator = null): PontoAcesso
    {
        return DB::transaction(function () use ($ponto, $novo, $motivo, $ator): PontoAcesso {
            $ponto = PontoAcesso::query()->lockForUpdate()->findOrFail($ponto->id);
            $anterior = $ponto->status;

            if ($anterior === StatusPontoAcesso::Inativo) {
                throw new RegraIntegracaoViolada('ponto_inativo', 'Ponto inativo não é reativado por alteração de situação; cadastre um novo ponto.');
            }

            if ($novo === StatusPontoAcesso::Inativo) {
                $this->encerrarVinculoVigente(ponto: $ponto, motivo: "ponto inativado: {$motivo}", ator: $ator);
            }

            $ponto->forceFill([
                'status' => $novo,
                'versao' => $ponto->versao + 1,
                'updated_by' => $ator?->id,
                'inactivated_at' => $novo === StatusPontoAcesso::Inativo ? now() : null,
            ])->save();

            $this->auditar('ponto_acesso_status_alterado', 'pontos_acesso', $ponto->id, [
                'status' => ['old' => $anterior->value, 'new' => $novo->value],
            ], $motivo);

            return $ponto;
        });
    }

    /**
     * @param  array{nome: string, tipo: string, fabricante: string, modelo: string, numero_serie?: ?string,
     *     endereco_rede: string, porta_rede?: ?int, protocolo: string, firmware_versao?: ?string,
     *     adaptador: string, direcao: string, timeout_segundos?: int}  $dados
     */
    public function registrarEquipamento(array $dados, ?User $ator = null): Equipamento
    {
        $tipo = TipoEquipamento::tryFrom($dados['tipo'] ?? '')
            ?? throw new RegraIntegracaoViolada('tipo_equipamento_invalido', 'Esta fundação cadastra somente terminais faciais.');
        $direcao = Direcao::tryFrom($dados['direcao'] ?? '')
            ?? throw new RegraIntegracaoViolada('direcao_invalida', 'Direção deve ser entrada, saída ou bidirecional.');

        if (! $this->adaptadores->permitido($dados['adaptador'] ?? '')) {
            throw new RegraIntegracaoViolada('adaptador_indisponivel', 'Adaptador inexistente ou não permitido neste ambiente.');
        }

        $this->validarRede($dados['endereco_rede'] ?? '', $dados['porta_rede'] ?? null);
        $timeout = (int) ($dados['timeout_segundos'] ?? 5);
        $this->validarTimeout($timeout);

        return DB::transaction(function () use ($dados, $tipo, $direcao, $timeout, $ator): Equipamento {
            $this->garantirSerieUnica($dados['numero_serie'] ?? null);

            $equipamento = Equipamento::query()->create([
                'nome' => $dados['nome'],
                'tipo' => $tipo,
                'fabricante' => $dados['fabricante'],
                'modelo' => $dados['modelo'],
                'numero_serie' => $dados['numero_serie'] ?? null,
                'endereco_rede' => $dados['endereco_rede'],
                'porta_rede' => $dados['porta_rede'] ?? null,
                'protocolo' => $dados['protocolo'],
                'firmware_versao' => $dados['firmware_versao'] ?? null,
                'adaptador' => $dados['adaptador'],
                'direcao' => $direcao,
                'timeout_segundos' => $timeout,
                'status' => StatusEquipamento::NaoConfigurado,
                'estado_saude' => EstadoSaude::Desconhecido,
                'created_by' => $ator?->id,
                'updated_by' => $ator?->id,
            ]);

            $this->auditar('equipamento_cadastrado', 'equipamentos', $equipamento->id, collect([
                'nome', 'tipo', 'fabricante', 'modelo', 'numero_serie', 'endereco_rede',
                'porta_rede', 'protocolo', 'firmware_versao', 'adaptador', 'direcao',
            ])->mapWithKeys(fn (string $campo) => [$campo => ['old' => null, 'new' => $this->valor($equipamento->getAttribute($campo))]])->all());

            return $equipamento;
        });
    }

    /** @param  array<string, mixed>  $dados  somente campos de inventário */
    public function atualizarInventario(Equipamento $equipamento, array $dados, int $versaoEsperada, ?User $ator = null): Equipamento
    {
        $dados = array_intersect_key($dados, array_flip(self::CAMPOS_INVENTARIO));

        if (array_key_exists('endereco_rede', $dados) || array_key_exists('porta_rede', $dados)) {
            $this->validarRede($dados['endereco_rede'] ?? $equipamento->endereco_rede, $dados['porta_rede'] ?? $equipamento->porta_rede);
        }
        if (array_key_exists('timeout_segundos', $dados)) {
            $this->validarTimeout((int) $dados['timeout_segundos']);
        }
        if (array_key_exists('direcao', $dados)) {
            $dados['direcao'] = Direcao::tryFrom((string) $dados['direcao'])
                ?? throw new RegraIntegracaoViolada('direcao_invalida', 'Direção deve ser entrada, saída ou bidirecional.');
        }

        return DB::transaction(function () use ($equipamento, $dados, $versaoEsperada, $ator): Equipamento {
            $equipamento = Equipamento::query()->lockForUpdate()->findOrFail($equipamento->id);
            $this->garantirVersao($equipamento->versao, $versaoEsperada);

            if (array_key_exists('numero_serie', $dados) && $dados['numero_serie'] !== $equipamento->numero_serie) {
                $this->garantirSerieUnica($dados['numero_serie']);
            }
            if (isset($dados['direcao']) && ($ponto = $equipamento->pontoVigente()) !== null) {
                $this->garantirDirecaoCompativel($dados['direcao'], $ponto);
            }

            $equipamento->fill($dados);
            $mudancas = collect($equipamento->getDirty())
                ->mapWithKeys(fn ($novo, string $campo) => [$campo => [
                    'old' => $this->valor($equipamento->getOriginal($campo)),
                    'new' => $this->valor($equipamento->getAttribute($campo)),
                ]])->all();

            if ($mudancas === []) {
                return $equipamento;
            }

            $equipamento->forceFill(['versao' => $equipamento->versao + 1, 'updated_by' => $ator?->id])->save();

            if (isset($mudancas['firmware_versao'])) {
                // Capacidades verificadas valem para um firmware; trocar o
                // firmware exige nova verificação (ADR-016, CA-ADR-016-008).
                $equipamento->capacidades()->update([
                    'suportada' => false,
                    'motivo_ausencia' => 'firmware alterado; verificar novamente',
                ]);
            }

            $this->auditar('equipamento_inventario_alterado', 'equipamentos', $equipamento->id, $mudancas);

            return $equipamento;
        });
    }

    /** Vínculo exclusivo terminal ↔ ponto (ADR-016 §5, CA-ADR-016-001). */
    public function vincularAoPonto(Equipamento $equipamento, PontoAcesso $ponto, ?User $ator = null): EquipamentoPontoVinculo
    {
        try {
            return DB::transaction(function () use ($equipamento, $ponto, $ator): EquipamentoPontoVinculo {
                $equipamento = Equipamento::query()->lockForUpdate()->findOrFail($equipamento->id);
                $ponto = PontoAcesso::query()->lockForUpdate()->findOrFail($ponto->id);

                if ($equipamento->status === StatusEquipamento::Inativo) {
                    throw new RegraIntegracaoViolada('equipamento_inativo', 'Equipamento inativo não pode ser vinculado.');
                }
                if ($ponto->status === StatusPontoAcesso::Inativo) {
                    throw new RegraIntegracaoViolada('ponto_inativo', 'Ponto de acesso inativo não pode receber equipamento.');
                }

                $atual = $equipamento->vinculoVigente;
                if ($atual !== null) {
                    throw new RegraIntegracaoViolada(
                        $atual->ponto_acesso_id === $ponto->id ? 'vinculo_ja_existente' : 'equipamento_ja_vinculado',
                        'Este terminal já atende um ponto de acesso. Encerre o vínculo atual antes de criar outro.'
                    );
                }
                if ($ponto->vinculoVigente()->exists()) {
                    throw new RegraIntegracaoViolada('ponto_ja_atendido', 'Este ponto de acesso já é atendido por outro terminal.');
                }

                $this->garantirDirecaoCompativel($equipamento->direcao, $ponto);

                $vinculo = EquipamentoPontoVinculo::query()->create([
                    'equipamento_id' => $equipamento->id,
                    'ponto_acesso_id' => $ponto->id,
                    'started_at' => now(),
                    'created_by' => $ator?->id,
                ]);

                $this->auditar('equipamento_vinculado_ao_ponto', 'equipamentos', $equipamento->id, [
                    'ponto_acesso_id' => ['old' => null, 'new' => $ponto->id],
                ], metadata: ['vinculo_id' => $vinculo->id, 'ponto_codigo' => $ponto->codigo]);

                return $vinculo;
            });
        } catch (UniqueConstraintViolationException) {
            // Duas requisições simultâneas: o índice parcial do banco é a última defesa.
            throw new RegraIntegracaoViolada('vinculo_concorrente', 'Outro vínculo foi criado ao mesmo tempo para este terminal ou ponto. Consulte a situação atual.');
        }
    }

    public function desvincularDoPonto(Equipamento $equipamento, string $motivo, ?User $ator = null): EquipamentoPontoVinculo
    {
        return DB::transaction(function () use ($equipamento, $motivo, $ator): EquipamentoPontoVinculo {
            $equipamento = Equipamento::query()->lockForUpdate()->findOrFail($equipamento->id);

            return $this->encerrarVinculoVigente(equipamento: $equipamento, motivo: $motivo, ator: $ator)
                ?? throw new RegraIntegracaoViolada('sem_vinculo', 'Este terminal não possui ponto de acesso vigente.');
        });
    }

    /**
     * Registra onde o segredo técnico está guardado. O valor nunca passa
     * por aqui; substituir cria nova referência e preserva a anterior.
     */
    public function definirCredencialTecnica(Equipamento $equipamento, string $referenciaSegredo, ?string $usuarioTecnico = null, ?User $ator = null): EquipamentoCredencial
    {
        $this->segredos->validarReferencia($referenciaSegredo);

        return DB::transaction(function () use ($equipamento, $referenciaSegredo, $usuarioTecnico, $ator): EquipamentoCredencial {
            $equipamento = Equipamento::query()->lockForUpdate()->findOrFail($equipamento->id);
            $anterior = $equipamento->credencialTecnicaAtiva();

            $anterior?->forceFill(['status' => 'substituida', 'substituida_em' => now()])->save();

            $credencial = EquipamentoCredencial::query()->create([
                'equipamento_id' => $equipamento->id,
                'finalidade' => 'administracao',
                'usuario_tecnico' => $usuarioTecnico,
                'referencia_segredo' => $referenciaSegredo,
                'status' => 'ativa',
                'definida_em' => now(),
                'created_by' => $ator?->id,
            ]);

            $this->auditar(
                $anterior === null ? 'equipamento_credencial_definida' : 'equipamento_credencial_substituida',
                'equipamentos',
                $equipamento->id,
                ['referencia_segredo' => ['old' => $anterior ? 'anterior' : null, 'new' => 'nova']],
                metadata: ['credencial_tecnica_id' => $credencial->id],
                classification: 'restrita',
            );

            return $credencial;
        });
    }

    public function alterarStatus(Equipamento $equipamento, StatusEquipamento $novo, string $motivo, ?User $ator = null): Equipamento
    {
        return DB::transaction(function () use ($equipamento, $novo, $motivo, $ator): Equipamento {
            $equipamento = Equipamento::query()->lockForUpdate()->findOrFail($equipamento->id);
            $anterior = $equipamento->status;

            if ($anterior === StatusEquipamento::Inativo) {
                throw new RegraIntegracaoViolada('equipamento_inativo', 'Equipamento inativo não é reativado; cadastre-o novamente para preservar o histórico.');
            }

            if ($novo === StatusEquipamento::Ativo) {
                $this->garantirProntoParaAtivar($equipamento);
            }

            if ($novo === StatusEquipamento::Inativo) {
                $this->encerrarVinculoVigente(equipamento: $equipamento, motivo: "equipamento inativado: {$motivo}", ator: $ator);
            }

            $equipamento->forceFill([
                'status' => $novo,
                'versao' => $equipamento->versao + 1,
                'updated_by' => $ator?->id,
                'inactivated_at' => $novo === StatusEquipamento::Inativo ? now() : null,
            ])->save();

            $this->auditar('equipamento_status_alterado', 'equipamentos', $equipamento->id, [
                'status' => ['old' => $anterior->value, 'new' => $novo->value],
            ], $motivo);

            return $equipamento;
        });
    }

    // ---- Regras ------------------------------------------------------------

    private function garantirProntoParaAtivar(Equipamento $equipamento): void
    {
        if ($equipamento->vinculoVigente === null) {
            throw new RegraIntegracaoViolada('sem_ponto', 'Vincule o terminal a um ponto de acesso antes de ativá-lo.');
        }
        if ($equipamento->adaptador !== 'simulador' && $equipamento->credencialTecnicaAtiva() === null) {
            throw new RegraIntegracaoViolada('sem_credencial_tecnica', 'Defina a referência da credencial técnica antes de ativar o terminal.');
        }
        if (! $equipamento->capacidades()->exists()) {
            throw new RegraIntegracaoViolada('capacidades_nao_verificadas', 'Consulte as capacidades do terminal antes de ativá-lo.');
        }
        if ($equipamento->firmware_versao === null) {
            throw new RegraIntegracaoViolada('firmware_nao_inventariado', 'Informe a versão de firmware antes de ativar o terminal (CA-ADR-016-008).');
        }
    }

    private function encerrarVinculoVigente(?Equipamento $equipamento = null, ?PontoAcesso $ponto = null, string $motivo = '', ?User $ator = null): ?EquipamentoPontoVinculo
    {
        $vinculo = $equipamento?->vinculoVigente ?? $ponto?->vinculoVigente;
        if ($vinculo === null) {
            return null;
        }

        $vinculo->forceFill([
            'ended_at' => now(),
            'ended_by' => $ator?->id,
            'motivo_encerramento' => $motivo,
        ])->save();

        $this->auditar('equipamento_desvinculado_do_ponto', 'equipamentos', $vinculo->equipamento_id, [
            'ponto_acesso_id' => ['old' => $vinculo->ponto_acesso_id, 'new' => null],
        ], $motivo, ['vinculo_id' => $vinculo->id]);

        return $vinculo;
    }

    private function garantirDirecaoCompativel(Direcao $direcaoEquipamento, PontoAcesso $ponto): void
    {
        if (! $direcaoEquipamento->compativelCom($ponto->direcao_suportada)) {
            throw new RegraIntegracaoViolada('direcao_incompativel', 'A direção do terminal não é suportada por este ponto de acesso.');
        }
    }

    /**
     * O terminal fica em rede local segmentada e nunca publicado na internet
     * (ADR-016 §8). Endereço público ou nome de host são recusados.
     */
    private function validarRede(string $endereco, mixed $porta): void
    {
        if (filter_var($endereco, FILTER_VALIDATE_IP) === false) {
            throw new RegraIntegracaoViolada('endereco_invalido', 'Informe o IP fixo ou reservado do terminal.');
        }

        if (filter_var($endereco, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
            throw new RegraIntegracaoViolada('endereco_publico', 'O terminal deve estar em rede local; endereço público não é aceito.');
        }

        if ($porta !== null && (! is_int($porta) || $porta < 1 || $porta > 65535)) {
            throw new RegraIntegracaoViolada('porta_invalida', 'Porta de rede deve estar entre 1 e 65535.');
        }
    }

    private function validarTimeout(int $timeout): void
    {
        if ($timeout < 1 || $timeout > 60) {
            throw new RegraIntegracaoViolada('timeout_invalido', 'O tempo limite deve ficar entre 1 e 60 segundos.');
        }
    }

    private function garantirSerieUnica(?string $serie): void
    {
        if ($serie !== null && Equipamento::query()->where('numero_serie', $serie)->exists()) {
            throw new RegraIntegracaoViolada('serie_duplicada', 'Já existe um equipamento com este número de série nesta implantação.');
        }
    }

    private function garantirVersao(int $atual, int $esperada): void
    {
        if ($atual !== $esperada) {
            throw new RegraIntegracaoViolada('versao_desatualizada', 'O equipamento foi alterado por outra pessoa. Recarregue antes de salvar.');
        }
    }

    private function valor(mixed $valor): mixed
    {
        return $valor instanceof \BackedEnum ? $valor->value : $valor;
    }

    /**
     * @param  array<string, array{old: mixed, new: mixed}>  $mudancas
     * @param  array<string, mixed>  $metadata
     */
    private function auditar(string $acao, string $entidade, string $id, array $mudancas, ?string $justificativa = null, array $metadata = [], string $classification = 'interna'): void
    {
        $this->audit->record(
            action: $acao,
            module: 'integracoes',
            entityType: $entidade,
            entityId: $id,
            changes: $mudancas,
            justification: $justificativa,
            classification: $classification,
            metadata: $metadata,
        );
    }
}
