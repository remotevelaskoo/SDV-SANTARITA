<?php

namespace Tests\Feature\Integracoes;

use App\Integracoes\Adaptadores\Simulador\CenarioSimulador;
use App\Integracoes\Aplicacao\ComandosAbertura;
use App\Integracoes\Aplicacao\DespachanteOutbox;
use App\Integracoes\Aplicacao\DiagnosticoEquipamento;
use App\Integracoes\Aplicacao\OutboxIntegracao;
use App\Integracoes\Aplicacao\ProcessadorOperacoes;
use App\Integracoes\Aplicacao\Reconciliador;
use App\Integracoes\Aplicacao\SincronizacaoCredenciais;
use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\EstadoOutbox;
use App\Integracoes\Dominio\Enums\EstadoSincronizacao;
use App\Integracoes\Dominio\Enums\OperacaoIntegracao as Tipo;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;
use App\Integracoes\Dominio\Excecoes\PayloadDivergente;
use App\Jobs\ProcessarOperacaoIntegracao;
use App\Models\AuditoriaEvento;
use App\Models\CredencialSincronizacao;
use App\Models\Equipamento;
use App\Models\EquipamentoCapacidade;
use App\Models\EquipamentoEventoRecebido;
use App\Models\Implantacao;
use App\Models\IntegracaoOcorrencia;
use App\Models\OperacaoIntegracao;
use App\Models\ReferenciaExterna;
use App\Models\User;
use App\Support\ImplantacaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\TestCase;

class OutboxEProcessamentoTest extends TestCase
{
    use MontaCenarioIntegracao, RefreshDatabase;

    /** @var list<string> */
    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararIntegracao();
        Event::listen(MessageLogged::class, function (MessageLogged $e) {
            $this->logs[] = $e->message.' '.json_encode($e->context);
        });
    }

    protected function tearDown(): void
    {
        $this->encerrarIntegracao();
        parent::tearDown();
    }

    private function sincronizacao(): SincronizacaoCredenciais
    {
        return app(SincronizacaoCredenciais::class);
    }

    private function estadoSincronizacao(Equipamento $equipamento, string $credencialId): EstadoSincronizacao
    {
        return CredencialSincronizacao::query()
            ->where('equipamento_id', $equipamento->id)
            ->where('credencial_id', $credencialId)
            ->firstOrFail()->estado;
    }

    // ---- Outbox e idempotência -------------------------------------------

    public function test_mesma_chave_e_mesmo_conteudo_devolve_a_mesma_operacao(): void
    {
        $terminal = $this->terminalAtivo();
        $credencial = $this->credencial();

        $a = $this->sincronizacao()->solicitarSincronizacao($terminal, $credencial, 'sync-1');
        $b = $this->sincronizacao()->solicitarSincronizacao($terminal, $credencial, 'sync-1');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, OperacaoIntegracao::query()->where('operacao', Tipo::SincronizarCredencial->value)->count());
    }

    public function test_mesma_chave_com_conteudo_diferente_falha(): void
    {
        $terminal = $this->terminalAtivo();
        $this->sincronizacao()->solicitarSincronizacao($terminal, $this->credencial(), 'sync-1');

        $this->expectException(PayloadDivergente::class);
        $this->sincronizacao()->solicitarSincronizacao($terminal, $this->credencial(), 'sync-1');
    }

    public function test_job_so_e_publicado_depois_do_commit(): void
    {
        $terminal = $this->terminalAtivo();
        $this->usarFilaReal();

        try {
            DB::transaction(function () use ($terminal) {
                $this->sincronizacao()->solicitarSincronizacao($terminal, $this->credencial(), 'sync-rollback');
                $this->assertSame(0, $this->simulador()->chamadas($terminal->id, Tipo::SincronizarCredencial), 'Efeito externo dentro da transação.');
                throw new \RuntimeException('falha no caso de uso');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, OperacaoIntegracao::query()->where('chave_idempotencia', 'sync-rollback')->count());
        $this->assertSame(0, $this->simulador()->chamadas($terminal->id, Tipo::SincronizarCredencial));

        $this->sincronizacao()->solicitarSincronizacao($terminal, $this->credencial(), 'sync-ok');
        $this->assertSame(1, $this->simulador()->chamadas($terminal->id, Tipo::SincronizarCredencial));
    }

    public function test_outbox_recusa_payload_com_segredo_ou_imagem(): void
    {
        $terminal = $this->terminalAtivo();

        foreach (['senha' => 'x', 'imagem_facial' => 'x', 'template' => 'x', 'aninhado' => null] as $campo => $valor) {
            $payload = $campo === 'aninhado' ? ['dados' => ['a' => 1]] : [$campo => $valor];
            $this->assertRegraViolada('payload_proibido', fn () => app(OutboxIntegracao::class)->registrar($terminal, Tipo::TestarConexao, $payload, 'chave-'.$campo));
        }
    }

    public function test_job_em_fila_carrega_somente_identificadores(): void
    {
        $terminal = $this->terminalAtivo();
        $operacao = $this->sincronizacao()->solicitarSincronizacao($terminal, $this->credencial(), 'sync-ids');

        Queue::assertPushedOn('integrations', ProcessarOperacaoIntegracao::class);
        Queue::assertPushed(ProcessarOperacaoIntegracao::class, function (ProcessarOperacaoIntegracao $job) use ($operacao) {
            return $job->operacaoId === $operacao->id &&
                $job->implantacaoId === $this->implantacao->id &&
                ! str_contains(serialize($job), 'Pessoa Sintética');
        });
    }

    // ---- Sincronização --------------------------------------------------

    public function test_sincronizacao_confirmada_guarda_id_externo_como_referencia_secundaria(): void
    {
        $terminal = $this->terminalAtivo();
        $credencial = $this->credencial();

        $operacao = $this->processar($this->sincronizacao()->solicitarSincronizacao($terminal, $credencial, 'sync-ok'));

        $this->assertSame(EstadoOutbox::Processado, $operacao->estado);
        $this->assertSame(ResultadoEquipamento::Confirmado, $operacao->resultado);
        $this->assertSame(EstadoSincronizacao::Sincronizado, $this->estadoSincronizacao($terminal, $credencial->credencialId));

        $referencia = ReferenciaExterna::query()->where('entidade_id', $credencial->credencialId)->firstOrFail();
        $this->assertTrue(Str::isUuid($referencia->entidade_id));
        $this->assertStringStartsWith('SIM-', $referencia->id_externo);
        $this->assertNotSame($referencia->id_externo, $credencial->credencialId);
        $this->assertNotNull($terminal->refresh()->ultima_comunicacao_at);
        $this->assertTrue(AuditoriaEvento::query()->where('action', 'integracao_sincronizar_credencial')->where('result', 'sucesso')->exists());
    }

    public function test_reprocessar_operacao_ja_concluida_nao_repete_o_efeito(): void
    {
        $terminal = $this->terminalAtivo();
        $operacao = $this->processar($this->sincronizacao()->solicitarSincronizacao($terminal, $this->credencial(), 'sync-uma-vez'));

        $this->processar($operacao);
        (new ProcessarOperacaoIntegracao($this->implantacao->id, $operacao->id))->handle(app(ProcessadorOperacoes::class));

        $this->assertSame(1, $this->simulador()->chamadas($terminal->id, Tipo::SincronizarCredencial));
        $this->assertSame(1, $operacao->refresh()->tentativas);
    }

    public function test_timeout_nao_e_registrado_como_sucesso_e_vira_reconciliacao(): void
    {
        $terminal = $this->terminalAtivo();
        $credencial = $this->credencial();
        $this->simulador()->definirCenario($terminal->id, CenarioSimulador::CallbackTardio, Tipo::SincronizarCredencial);

        $operacao = $this->processar($this->sincronizacao()->solicitarSincronizacao($terminal, $credencial, 'sync-timeout'));

        $this->assertSame(ResultadoEquipamento::ConfirmacaoDesconhecida, $operacao->resultado);
        $this->assertSame(EstadoSincronizacao::AtualizacaoPendente, $this->estadoSincronizacao($terminal, $credencial->credencialId));
        $this->assertFalse(AuditoriaEvento::query()->where('action', 'integracao_sincronizar_credencial')->where('result', 'sucesso')->exists());
        $this->assertSame(1, $this->simulador()->chamadas($terminal->id, Tipo::SincronizarCredencial), 'Timeout não pode gerar reenvio automático.');

        // Reconciliação consulta o terminal em vez de reenviar.
        $this->assertSame(['consultas' => 1, 'intervencoes' => 0], app(Reconciliador::class)->reconciliar($terminal));
        $consulta = OperacaoIntegracao::query()->where('operacao', Tipo::ConsultarSincronizacao->value)->firstOrFail();
        $this->processar($consulta);

        $this->assertSame(EstadoSincronizacao::Sincronizado, $this->estadoSincronizacao($terminal, $credencial->credencialId));
        $this->assertSame($operacao->id, $consulta->causation_id);
        $this->assertSame(1, $this->simulador()->chamadas($terminal->id, Tipo::SincronizarCredencial));

        // Reconciliar de novo não duplica a consulta.
        app(Reconciliador::class)->reconciliar($terminal);
        $this->assertSame(1, OperacaoIntegracao::query()->where('operacao', Tipo::ConsultarSincronizacao->value)->count());
    }

    public function test_resultado_desconhecido_sem_capacidade_de_consulta_exige_intervencao(): void
    {
        $terminal = $this->terminalAtivo();
        $credencial = $this->credencial();
        EquipamentoCapacidade::query()->where('capacidade', Capacidade::ConsultarSincronizacao->value)->update(['suportada' => false]);
        $this->simulador()->definirCenario($terminal->id, CenarioSimulador::Timeout, Tipo::SincronizarCredencial);

        $this->processar($this->sincronizacao()->solicitarSincronizacao($terminal, $credencial, 'sync-sem-prova'));
        $this->assertSame(['consultas' => 0, 'intervencoes' => 1], app(Reconciliador::class)->reconciliar($terminal));

        $this->assertSame(EstadoSincronizacao::IntervencaoNecessaria, $this->estadoSincronizacao($terminal, $credencial->credencialId));
    }

    public function test_indisponibilidade_reagenda_com_backoff_e_depois_exige_intervencao(): void
    {
        config(['integracoes.outbox.max_tentativas' => 2]);
        $terminal = $this->terminalAtivo();
        $credencial = $this->credencial();
        $this->simulador()->definirCenario($terminal->id, CenarioSimulador::Indisponivel, Tipo::SincronizarCredencial);

        $operacao = $this->processar($this->sincronizacao()->solicitarSincronizacao($terminal, $credencial, 'sync-queda'));

        $this->assertSame(EstadoOutbox::FalhaTemporaria, $operacao->estado);
        $this->assertTrue($operacao->disponivel_em->isFuture());
        $this->assertSame(EstadoSincronizacao::Aguardando, $this->estadoSincronizacao($terminal, $credencial->credencialId));

        // Antes do horário de retentativa nada acontece.
        $this->assertSame(1, $this->processar($operacao)->tentativas);

        $this->travel(15)->minutes();
        $operacao = $this->processar($operacao);

        $this->assertSame(2, $operacao->tentativas);
        $this->assertSame(EstadoOutbox::IntervencaoNecessaria, $operacao->estado);
        $this->assertSame(EstadoSincronizacao::IntervencaoNecessaria, $this->estadoSincronizacao($terminal, $credencial->credencialId));
        $this->assertSame(2, IntegracaoOcorrencia::query()->where('operacao_integracao_id', $operacao->id)->count());
    }

    public function test_excecao_do_adaptador_vira_falha_sanitizada_sem_vazar_segredo(): void
    {
        $terminal = $this->terminalAtivo();
        $this->simulador()->definirCenario($terminal->id, CenarioSimulador::Excecao, Tipo::SincronizarCredencial);

        $operacao = $this->processar($this->sincronizacao()->solicitarSincronizacao($terminal, $this->credencial(), 'sync-excecao'));

        $this->assertSame(ResultadoEquipamento::FalhaTecnica, $operacao->resultado);
        $this->assertStringNotContainsString('SIMULADA-NAO-DEVE-VAZAR', (string) $operacao->erro_sanitizado);
        $this->assertStringContainsString('[REDIGIDO]', (string) $operacao->erro_sanitizado);
        $this->assertFalse(IntegracaoOcorrencia::query()->where('mensagem_sanitizada', 'like', '%NAO-DEVE-VAZAR%')->exists());
        $this->assertStringNotContainsString('NAO-DEVE-VAZAR', implode("\n", $this->logs));
    }

    public function test_nenhum_log_ou_registro_contem_a_senha_do_terminal(): void
    {
        $terminal = $this->terminalAtivo();
        $this->processar(app(DiagnosticoEquipamento::class)->testarConexao($terminal));
        $this->processar($this->sincronizacao()->solicitarSincronizacao($terminal, $this->credencial(), 'sync-log'));

        $this->assertNotEmpty($this->logs);
        $this->assertStringNotContainsString('Senha-Terminal-Teste-987', implode("\n", $this->logs));
        foreach (['operacoes_integracao', 'integracao_ocorrencias', 'auditoria_contextos', 'auditoria_alteracoes', 'equipamentos', 'equipamento_credenciais'] as $tabela) {
            $this->assertStringNotContainsString('Senha-Terminal-Teste-987', json_encode(DB::table($tabela)->get()), $tabela);
        }
    }

    public function test_credencial_facial_e_bloqueada_pelo_adr_013(): void
    {
        $terminal = $this->terminalAtivo();

        $this->assertRegraViolada('biometria_bloqueada_adr013', fn () => $this->sincronizacao()->solicitarSincronizacao($terminal, $this->credencial(['tipo' => 'face']), 'sync-face'));
        $this->assertSame(0, OperacaoIntegracao::query()->where('operacao', Tipo::SincronizarCredencial->value)->count());

        // Mesmo se uma operação facial chegar à outbox por outro caminho, o processador recusa.
        $forcada = app(OutboxIntegracao::class)->registrar($terminal, Tipo::SincronizarCredencial, [
            'credencial_id' => (string) Str::uuid7(), 'tipo' => 'face', 'titular_referencia' => 'x', 'nome_exibicao' => 'x',
            'vigencia_inicio' => now()->toAtomString(), 'vigencia_fim' => null, 'direcao' => 'entrada',
        ], 'sync-face-forcada');
        $this->processar($forcada);
        $this->assertSame('biometria_bloqueada_adr013', IntegracaoOcorrencia::query()->where('operacao_integracao_id', $forcada->id)->value('codigo'));
        $this->assertSame(0, $this->simulador()->chamadas($terminal->id, Tipo::SincronizarCredencial));
    }

    public function test_credencial_expirada_nao_e_enviada(): void
    {
        $terminal = $this->terminalAtivo();

        $this->assertRegraViolada('credencial_expirada', fn () => $this->sincronizacao()->solicitarSincronizacao(
            $terminal,
            $this->credencial(['vigenciaFim' => new \DateTimeImmutable('-1 minute')]),
            'sync-expirada',
        ));
    }

    public function test_capacidade_ausente_nao_chega_ao_equipamento(): void
    {
        $terminal = $this->terminalAtivo();
        EquipamentoCapacidade::query()->where('capacidade', Capacidade::SincronizarCredencial->value)->update(['suportada' => false]);
        $credencial = $this->credencial();

        $operacao = $this->processar($this->sincronizacao()->solicitarSincronizacao($terminal, $credencial, 'sync-sem-capacidade'));

        $this->assertSame(ResultadoEquipamento::CapacidadeAusente, $operacao->resultado);
        $this->assertSame(EstadoSincronizacao::Falha, $this->estadoSincronizacao($terminal, $credencial->credencialId));
        $this->assertSame(0, $this->simulador()->chamadas($terminal->id, Tipo::SincronizarCredencial));
    }

    public function test_revogacao_remove_do_terminal_e_encerra_a_referencia_externa(): void
    {
        $terminal = $this->terminalAtivo();
        $credencial = $this->credencial();
        $this->processar($this->sincronizacao()->solicitarSincronizacao($terminal, $credencial, 'sync-antes'));

        $revogacao = $this->processar($this->sincronizacao()->solicitarRevogacao($terminal, $credencial->credencialId, 'fim do contrato', 'revogar-1'));

        $this->assertSame(ResultadoEquipamento::Confirmado, $revogacao->resultado);
        $this->assertSame(EstadoSincronizacao::Removido, $this->estadoSincronizacao($terminal, $credencial->credencialId));
        $this->assertArrayNotHasKey($credencial->credencialId, $this->simulador()->credenciaisGravadas($terminal->id));
        $this->assertNotNull(ReferenciaExterna::query()->where('entidade_id', $credencial->credencialId)->value('substituida_em'));
        $this->assertSame(1, ReferenciaExterna::query()->where('entidade_id', $credencial->credencialId)->count(), 'Histórico preservado.');
    }

    // ---- Saúde e eventos ---------------------------------------------------

    public function test_teste_de_conexao_atualiza_saude_e_ultima_comunicacao(): void
    {
        $terminal = $this->terminalAtivo();
        $diagnostico = app(DiagnosticoEquipamento::class);

        $this->processar($diagnostico->testarConexao($terminal));
        $this->assertSame('conectado', $terminal->refresh()->estado_saude->value);
        $comunicacao = $terminal->ultima_comunicacao_at;

        $this->travel(1)->minutes();
        $this->simulador()->definirCenario($terminal->id, CenarioSimulador::Indisponivel, Tipo::TestarConexao);
        $this->processar($diagnostico->testarConexao($terminal));

        $terminal->refresh();
        $this->assertSame('indisponivel', $terminal->estado_saude->value);
        $this->assertEquals($comunicacao, $terminal->ultima_comunicacao_at, 'Falha não avança a última comunicação.');
        $this->assertNotNull($terminal->ultimo_erro_sanitizado);
    }

    public function test_credencial_tecnica_ausente_resulta_em_credencial_invalida(): void
    {
        putenv('SDV_TESTE_TERMINAL_SENHA');
        $terminal = $this->terminalAtivo();

        $this->processar(app(DiagnosticoEquipamento::class)->testarConexao($terminal));

        $this->assertSame('credencial_invalida', $terminal->refresh()->estado_saude->value);
    }

    public function test_eventos_sao_deduplicados_com_instantes_separados_e_sem_imagem(): void
    {
        $terminal = $this->terminalAtivo();
        $this->simulador()->registrarEventoNoTerminal($terminal->id, [
            'serial' => '5001', 'tipo' => 'reconhecimento', 'instante' => now()->subMinutes(10)->toAtomString(),
            'direcao' => 'entrada', 'resultado' => 'autorizado_localmente', 'credencial' => 'SIM-ABC',
        ]);
        $this->simulador()->registrarEventoNoTerminal($terminal->id, ['tipo' => 'sem_identificador']);
        $this->simulador()->definirCenario($terminal->id, CenarioSimulador::Duplicidade, Tipo::ColetarEventos);

        $coleta = $this->processar(app(DiagnosticoEquipamento::class)->coletarEventos($terminal));

        $this->assertSame(['cursor' => '4', 'novos' => 1, 'duplicados' => 1, 'invalidos' => 2], $coleta->resultado_dados);
        $evento = EquipamentoEventoRecebido::query()->sole();
        $this->assertNotEquals($evento->ocorrido_no_equipamento_em, $evento->recebido_em);
        $this->assertSame(600, $evento->divergencia_relogio_segundos);

        // Uma segunda coleta com o mesmo evento não duplica.
        $this->simulador()->registrarEventoNoTerminal($terminal->id, ['serial' => '5001', 'tipo' => 'reconhecimento']);
        $this->processar(app(DiagnosticoEquipamento::class)->coletarEventos($terminal));
        $this->assertSame(1, EquipamentoEventoRecebido::query()->count());
    }

    // ---- Abertura remota ---------------------------------------------------

    public function test_abertura_remota_fica_desligada_por_padrao(): void
    {
        $terminal = $this->terminalAtivo();

        $this->assertRegraViolada('abertura_remota_desabilitada', fn () => app(ComandosAbertura::class)->solicitar(
            $terminal->pontoVigente(), (string) Str::uuid7(), 'abrir-1', User::factory()->create(), 'teste'
        ));
    }

    public function test_abertura_exige_decisao_do_sdv_e_nao_duplica_com_a_mesma_chave(): void
    {
        config(['integracoes.abertura_remota_habilitada' => true]);
        $terminal = $this->terminalAtivo();
        $ponto = $terminal->pontoVigente();
        $operador = User::factory()->create();

        $this->assertRegraViolada('sem_decisao', fn () => app(ComandosAbertura::class)->solicitar($ponto, 'reconhecimento-facial', 'abrir-x', $operador, 'teste'));

        $decisao = (string) Str::uuid7();
        $a = app(ComandosAbertura::class)->solicitar($ponto, $decisao, 'abrir-1', $operador, 'visitante na cancela');
        $b = app(ComandosAbertura::class)->solicitar($ponto, $decisao, 'abrir-1', $operador, 'visitante na cancela');
        Queue::assertPushedOn('access-critical', ProcessarOperacaoIntegracao::class);

        $this->processar($a);
        $this->processar($b);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, $this->simulador()->totalAberturasFisicas($terminal->id));
        $this->assertSame(ResultadoEquipamento::Confirmado, $a->refresh()->resultado);
        $this->assertSame(1, AuditoriaEvento::query()->where('action', 'abertura_remota_solicitada')->count());
    }

    public function test_timeout_na_abertura_nunca_reenvia_e_reconcilia_por_consulta(): void
    {
        config(['integracoes.abertura_remota_habilitada' => true]);
        $terminal = $this->terminalAtivo();
        $this->simulador()->definirCenario($terminal->id, CenarioSimulador::CallbackTardio, Tipo::AberturaRemota);

        $comando = $this->processar(app(ComandosAbertura::class)->solicitar($terminal->pontoVigente(), (string) Str::uuid7(), 'abrir-timeout', User::factory()->create(), 'teste'));

        $this->assertSame(ResultadoEquipamento::ConfirmacaoDesconhecida, $comando->resultado);
        $this->assertSame(1, $comando->max_tentativas);

        $this->travel(5)->minutes();
        app(DespachanteOutbox::class)->despachar();
        $this->processar($comando);
        $this->assertSame(1, $this->simulador()->chamadas($terminal->id, Tipo::AberturaRemota));

        app(Reconciliador::class)->reconciliar($terminal);
        $this->processar(OperacaoIntegracao::query()->where('operacao', Tipo::ConsultarResultadoComando->value)->sole());

        $this->assertSame(ResultadoEquipamento::Confirmado, $comando->refresh()->resultado);
        $this->assertSame(1, $this->simulador()->totalAberturasFisicas($terminal->id));
    }

    public function test_abertura_desconhecida_sem_prova_vai_para_intervencao(): void
    {
        config(['integracoes.abertura_remota_habilitada' => true]);
        $terminal = $this->terminalAtivo();
        EquipamentoCapacidade::query()->where('capacidade', Capacidade::ConsultarResultadoComando->value)->update(['suportada' => false]);
        $this->simulador()->definirCenario($terminal->id, CenarioSimulador::Timeout, Tipo::AberturaRemota);

        $comando = $this->processar(app(ComandosAbertura::class)->solicitar($terminal->pontoVigente(), (string) Str::uuid7(), 'abrir-sem-prova', User::factory()->create(), 'teste'));

        $this->assertSame(EstadoOutbox::IntervencaoNecessaria, $comando->estado);
        $this->assertSame(ResultadoEquipamento::ConfirmacaoDesconhecida, $comando->resultado);
    }

    public function test_comando_expirado_nao_e_enviado(): void
    {
        config(['integracoes.abertura_remota_habilitada' => true]);
        $terminal = $this->terminalAtivo();
        $comando = app(ComandosAbertura::class)->solicitar($terminal->pontoVigente(), (string) Str::uuid7(), 'abrir-atrasado', User::factory()->create(), 'teste');

        $this->travel(1)->minutes();
        $comando = $this->processar($comando);

        $this->assertSame(ResultadoEquipamento::Expirado, $comando->resultado);
        $this->assertSame(0, $this->simulador()->chamadas($terminal->id, Tipo::AberturaRemota));
    }

    public function test_worker_interrompido_durante_abertura_nao_reenvia(): void
    {
        config(['integracoes.abertura_remota_habilitada' => true]);
        $terminal = $this->terminalAtivo();
        $comando = app(ComandosAbertura::class)->solicitar($terminal->pontoVigente(), (string) Str::uuid7(), 'abrir-lease', User::factory()->create(), 'teste');
        $comando->forceFill(['estado' => EstadoOutbox::Processando, 'lease_ate' => now()->subSecond(), 'lease_token' => (string) Str::uuid7()])->save();

        $comando = $this->processar($comando);

        $this->assertSame(EstadoOutbox::IntervencaoNecessaria, $comando->estado);
        $this->assertSame(ResultadoEquipamento::ConfirmacaoDesconhecida, $comando->resultado);
        $this->assertSame(0, $this->simulador()->chamadas($terminal->id, Tipo::AberturaRemota));
    }

    // ---- Implantação e fila -------------------------------------------------

    public function test_job_processa_no_contexto_da_implantacao_da_operacao(): void
    {
        $terminal = $this->terminalAtivo();
        $credencial = $this->credencial();
        $operacao = $this->sincronizacao()->solicitarSincronizacao($terminal, $credencial, 'sync-ctx');

        // O worker está "em outra implantação" quando recebe o job.
        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        (new ProcessarOperacaoIntegracao($this->implantacao->id, $operacao->id))->handle(app(ProcessadorOperacoes::class));
        ImplantacaoContext::setCurrentForTesting($this->implantacao);

        $this->assertSame(ResultadoEquipamento::Confirmado, $operacao->refresh()->resultado);
        $this->assertSame($this->implantacao->id, IntegracaoOcorrencia::query()->where('operacao_integracao_id', $operacao->id)->value('implantacao_id'));
        $this->assertSame($this->implantacao->id, ReferenciaExterna::query()->where('entidade_id', $credencial->credencialId)->value('implantacao_id'));
    }

    public function test_despachante_republica_operacoes_elegiveis(): void
    {
        $terminal = $this->terminalAtivo();
        $this->sincronizacao()->solicitarSincronizacao($terminal, $this->credencial(), 'sync-perdida');
        Queue::fake();

        $this->assertSame(1, app(DespachanteOutbox::class)->despachar());
        Queue::assertPushed(ProcessarOperacaoIntegracao::class, 1);
        $this->artisan('integracoes:despachar')->assertSuccessful();
    }

    public function test_fluxo_completo_com_fila_sincrona(): void
    {
        $this->usarFilaReal();
        $terminal = $this->terminalAtivo();
        $credencial = $this->credencial();

        $this->sincronizacao()->solicitarSincronizacao($terminal, $credencial, 'sync-fila-real');

        $this->assertSame(EstadoSincronizacao::Sincronizado, $this->estadoSincronizacao($terminal, $credencial->credencialId));
        $this->artisan('integracoes:reconciliar')->assertSuccessful();
    }
}
