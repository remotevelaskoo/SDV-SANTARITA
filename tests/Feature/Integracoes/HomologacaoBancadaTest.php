<?php

namespace Tests\Feature\Integracoes;

use App\Integracoes\Adaptadores\Simulador\CenarioSimulador;
use App\Integracoes\Aplicacao\CapturaImagemEquipamento;
use App\Integracoes\Aplicacao\ComandosAbertura;
use App\Integracoes\Aplicacao\DiagnosticoEquipamento;
use App\Integracoes\Aplicacao\ProcessadorOperacoes;
use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\EstadoOutbox;
use App\Integracoes\Dominio\Enums\OperacaoIntegracao;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Integracoes\Dominio\Enums\StatusPontoAcesso;
use App\Integracoes\Infra\ResolvedorSegredo;
use App\Models\AuditoriaEvento;
use App\Models\Equipamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\TestCase;

/**
 * Regras da primeira integração real em bancada: senha cifrada, teste de
 * conexão, captura de imagem e teste do relé, contra o simulador.
 */
class HomologacaoBancadaTest extends TestCase
{
    use MontaCenarioIntegracao, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararIntegracao();
        config(['integracoes.teste_rele_habilitado' => true]);
    }

    protected function tearDown(): void
    {
        $this->encerrarIntegracao();
        parent::tearDown();
    }

    private function operador(): User
    {
        return $this->usuarioCom('integracoes.gerenciar', ComandosAbertura::PERMISSAO_LIBERAR);
    }

    private function liberar(Equipamento $equipamento, ?User $ator = null, ?string $chave = null, string $motivo = 'Teste do relé na bancada')
    {
        return $this->processar(app(ComandosAbertura::class)->testarReleEmBancada(
            $equipamento, $chave ?? 'liberacao:'.Str::uuid7(), $ator ?? $this->operador(), $motivo,
        ));
    }

    // ---- Segredo --------------------------------------------------------------

    public function test_senha_cifrada_e_resolvida_somente_no_uso_e_trocada_por_substituicao(): void
    {
        $equipamento = $this->terminalEmHomologacao();
        $primeira = $equipamento->credencialTecnicaAtiva();

        $this->assertSame('Senha-Cifrada-Teste-321', app(ResolvedorSegredo::class)->resolverCredencial($primeira)->revelar());

        $this->cadastro()->definirSenhaTecnica($equipamento, 'admin', 'Nova-Senha-654');

        $this->assertSame('substituida', $primeira->refresh()->status);
        $this->assertSame('Nova-Senha-654', app(ResolvedorSegredo::class)->resolverCredencial($equipamento->credencialTecnicaAtiva())->revelar());
        $this->assertTrue(AuditoriaEvento::query()->where('action', 'equipamento_credencial_substituida')->exists());
    }

    public function test_senha_cifrada_e_recusada_quando_a_instalacao_exige_cofre(): void
    {
        config(['integracoes.segredos.cifrado_permitido' => false]);
        $equipamento = $this->novoEquipamento();

        $this->assertRegraViolada('segredo_cifrado_desabilitado', fn () => $this->cadastro()->definirSenhaTecnica($equipamento, 'admin', 'Qualquer-1'));
    }

    public function test_excecao_do_adaptador_nao_vaza_senha_em_log_nem_ocorrencia(): void
    {
        Log::spy();
        $equipamento = $this->terminalEmHomologacao();
        $this->cadastro()->definirSenhaTecnica($equipamento, 'admin', 'SIMULADA-NAO-DEVE-VAZAR');
        $this->simulador()->definirCenario($equipamento->id, CenarioSimulador::Excecao);

        $operacao = $this->processar(app(DiagnosticoEquipamento::class)->testarConexao($equipamento, $this->operador()));

        $this->assertStringNotContainsString('SIMULADA-NAO-DEVE-VAZAR', (string) $operacao->erro_sanitizado);
        $this->assertStringNotContainsString('SIMULADA-NAO-DEVE-VAZAR', (string) $equipamento->refresh()->ultimo_erro_sanitizado);
        Log::shouldHaveReceived('warning')->withArgs(fn ($m, $ctx) => ! str_contains(json_encode($ctx), 'SIMULADA-NAO-DEVE-VAZAR'));
    }

    // ---- Teste de conexão -----------------------------------------------------

    public function test_teste_manual_nao_repete_e_diferencia_falhas(): void
    {
        $equipamento = $this->terminalEmHomologacao();
        $diagnostico = app(DiagnosticoEquipamento::class);

        foreach ([
            [CenarioSimulador::Indisponivel, ResultadoEquipamento::Indisponivel, 'conexao_recusada', 'indisponivel'],
            [CenarioSimulador::Timeout, ResultadoEquipamento::ConfirmacaoDesconhecida, 'timeout', 'degradado'],
            [CenarioSimulador::CredencialInvalida, ResultadoEquipamento::Recusado, 'autenticacao_recusada', 'credencial_invalida'],
            [CenarioSimulador::CertificadoInvalido, ResultadoEquipamento::FalhaTecnica, 'certificado_invalido', 'degradado'],
        ] as [$cenario, $resultado, $codigo, $saude]) {
            $this->simulador()->definirCenario($equipamento->id, $cenario);
            $operacao = $this->processar($diagnostico->testarConexao($equipamento, $this->operador()));

            $this->assertSame($resultado, $operacao->resultado, $cenario->value);
            $this->assertSame(EstadoOutbox::Processado, $operacao->estado, $cenario->value);
            $this->assertSame(1, $operacao->max_tentativas);
            $this->assertSame($codigo, $operacao->ocorrencias()->latest('ocorrido_em')->value('codigo'), $cenario->value);
            $this->assertSame($saude, $equipamento->refresh()->estado_saude->value, $cenario->value);
            $this->assertNotNull($equipamento->ultima_falha_at);
        }
    }

    public function test_teste_bem_sucedido_atualiza_comunicacao_e_nao_salva_sucesso_falso(): void
    {
        $equipamento = $this->terminalEmHomologacao();
        $equipamento->forceFill(['ultima_comunicacao_at' => null])->save();
        $this->simulador()->definirCenario($equipamento->id, CenarioSimulador::Indisponivel);
        $this->processar(app(DiagnosticoEquipamento::class)->testarConexao($equipamento, $this->operador()));
        $this->assertNull($equipamento->refresh()->ultima_comunicacao_at);

        $this->simulador()->definirCenario($equipamento->id, CenarioSimulador::Sucesso);
        $this->processar(app(DiagnosticoEquipamento::class)->testarConexao($equipamento, $this->operador()));

        $this->assertNotNull($equipamento->refresh()->ultima_comunicacao_at);
        $this->assertSame('conectado', $equipamento->estado_saude->value);
    }

    // ---- Captura ----------------------------------------------------------------

    public function test_captura_valida_guarda_temporariamente_e_audita_sem_imagem(): void
    {
        $equipamento = $this->terminalEmHomologacao();
        $capturas = app(CapturaImagemEquipamento::class);

        $resultado = $capturas->capturar($equipamento, $this->operador());

        $this->assertSame('confirmado', $resultado['resultado']);
        $this->assertSame('image/png', $resultado['tipo_mime']);
        $this->assertNotNull($resultado['capturada_em']);
        $this->assertNotNull($capturas->ultima($equipamento));
        $this->assertNotNull($equipamento->refresh()->ultima_comunicacao_at);

        $evento = AuditoriaEvento::query()->where('action', 'equipamento_imagem_capturada')->with('context')->sole();
        $this->assertSame('sucesso', $evento->result);
        $this->assertStringNotContainsString('iVBOR', json_encode($evento->context->metadata));

        $this->travel(301)->seconds();
        $this->assertNull($capturas->ultima($equipamento));
    }

    public function test_captura_indisponivel_e_sem_capacidade_informam_claramente(): void
    {
        $equipamento = $this->terminalEmHomologacao();
        $capturas = app(CapturaImagemEquipamento::class);

        $this->simulador()->definirCenarioCaptura($equipamento->id, CenarioSimulador::CapturaIndisponivel);
        $indisponivel = $capturas->capturar($equipamento, $this->operador());
        $this->assertSame('imagem_indisponivel', $indisponivel['codigo']);
        $this->assertNull($capturas->ultima($equipamento));

        $equipamento->capacidades()->where('capacidade', Capacidade::CapturarImagem->value)->update(['suportada' => false]);
        $this->simulador()->definirCenarioCaptura($equipamento->id, CenarioSimulador::Sucesso);
        $ausente = $capturas->capturar($equipamento, $this->operador());

        $this->assertSame('capacidade_ausente', $ausente['resultado']);
        $this->assertNull($capturas->ultima($equipamento));
    }

    public function test_captura_exige_permissao_e_equipamento_ativo_no_cadastro(): void
    {
        $equipamento = $this->terminalEmHomologacao();

        $this->assertRegraViolada('sem_permissao', fn () => app(CapturaImagemEquipamento::class)->capturar($equipamento, $this->usuarioCom('dashboard.visualizar')));

        $this->cadastro()->alterarStatus($equipamento, StatusEquipamento::Inativo, 'fim do teste');
        $this->assertRegraViolada('equipamento_inativo', fn () => app(CapturaImagemEquipamento::class)->capturar($equipamento->refresh(), $this->operador()));
    }

    // ---- Liberação (teste do relé) --------------------------------------------

    public function test_liberacao_aceita_nao_vira_acesso_realizado_e_audita_operador_e_motivo(): void
    {
        $equipamento = $this->terminalEmHomologacao();
        $this->simulador()->definirCenario($equipamento->id, CenarioSimulador::Aceito);
        $operador = $this->operador();
        $this->actingAs($operador);

        $operacao = $this->liberar($equipamento, $operador, motivo: 'Validar pulso do relé');

        $this->assertSame(ResultadoEquipamento::Aceito, $operacao->resultado);
        $this->assertSame(ComandosAbertura::ORIGEM_TESTE_BANCADA, $operacao->origem);
        $this->assertSame(1, $operacao->max_tentativas);

        $solicitacao = AuditoriaEvento::query()->where('action', 'liberacao_remota_solicitada')->with('context')->sole();
        $this->assertSame($operador->id, $solicitacao->actor_id);
        $this->assertSame('Validar pulso do relé', $solicitacao->justification);
        $this->assertSame($equipamento->id, $solicitacao->entity_id);
        $this->assertArrayHasKey('ip_operador', $solicitacao->context->metadata);
        $this->assertSame('aceito', AuditoriaEvento::query()->where('action', 'integracao_abertura_remota')->value('result'));
        $this->assertFalse(AuditoriaEvento::query()->where('action', 'like', '%acesso_realizado%')->exists());
    }

    public function test_liberacao_recusada_desconhecida_e_tardia_nunca_sao_reenviadas(): void
    {
        $equipamento = $this->terminalEmHomologacao();

        $this->simulador()->definirCenario($equipamento->id, CenarioSimulador::Recusa);
        $this->assertSame(ResultadoEquipamento::Recusado, $this->liberar($equipamento)->resultado);

        $this->simulador()->definirCenario($equipamento->id, CenarioSimulador::ConfirmacaoDesconhecida);
        $desconhecida = $this->liberar($equipamento);
        $this->assertSame(ResultadoEquipamento::ConfirmacaoDesconhecida, $desconhecida->resultado);
        $this->assertNotSame(EstadoOutbox::FalhaTemporaria, $desconhecida->estado);

        $this->simulador()->definirCenario($equipamento->id, CenarioSimulador::CallbackTardio);
        $tardia = $this->liberar($equipamento);
        $this->assertSame(ResultadoEquipamento::ConfirmacaoDesconhecida, $tardia->resultado);

        // Reprocessar não reenvia nenhum dos três comandos.
        foreach ([$desconhecida, $tardia] as $operacao) {
            $this->assertNull(app(ProcessadorOperacoes::class)->processar($operacao->id));
        }
        $this->assertSame(3, $this->simulador()->chamadas($equipamento->id, OperacaoIntegracao::AberturaRemota));
    }

    public function test_mesma_chave_aciona_o_rele_uma_vez(): void
    {
        $equipamento = $this->terminalEmHomologacao();
        $chave = 'liberacao:'.Str::uuid7();
        $operador = $this->operador();

        $primeira = $this->liberar($equipamento, $operador, $chave);
        $segunda = $this->liberar($equipamento, $operador, $chave);

        $this->assertSame($primeira->id, $segunda->id);
        $this->assertSame(1, $this->simulador()->totalAberturasFisicas($equipamento->id));
        $this->assertSame(1, AuditoriaEvento::query()->where('action', 'liberacao_remota_solicitada')->count());
    }

    public function test_liberacao_exige_permissao_motivo_homologacao_capacidade_e_habilitacao(): void
    {
        $equipamento = $this->terminalEmHomologacao();
        $comandos = app(ComandosAbertura::class);
        $chave = fn () => 'liberacao:'.Str::uuid7();

        $this->assertRegraViolada('sem_permissao', fn () => $comandos->testarReleEmBancada($equipamento, $chave(), $this->usuarioCom('integracoes.gerenciar'), 'motivo válido'));
        $this->assertRegraViolada('sem_motivo', fn () => $comandos->testarReleEmBancada($equipamento, $chave(), $this->operador(), '   '));

        config(['integracoes.teste_rele_habilitado' => false]);
        $this->assertRegraViolada('teste_rele_desabilitado', fn () => $comandos->testarReleEmBancada($equipamento, $chave(), $this->operador(), 'motivo válido'));
        config(['integracoes.teste_rele_habilitado' => true]);

        $equipamento->capacidades()->where('capacidade', Capacidade::AberturaRemota->value)->update(['suportada' => false]);
        $this->assertRegraViolada('capacidade_ausente', fn () => $comandos->testarReleEmBancada($equipamento, $chave(), $this->operador(), 'motivo válido'));

        $this->cadastro()->alterarStatus($equipamento, StatusEquipamento::Manutencao, 'fim do teste');
        $this->assertRegraViolada('equipamento_fora_de_homologacao', fn () => $comandos->testarReleEmBancada($equipamento->refresh(), $chave(), $this->operador(), 'motivo válido'));
        $this->assertSame(0, $this->simulador()->totalAberturasFisicas($equipamento->id));
    }

    public function test_desligar_o_teste_depois_de_enfileirado_impede_o_envio(): void
    {
        $equipamento = $this->terminalEmHomologacao();
        $operacao = app(ComandosAbertura::class)->testarReleEmBancada($equipamento, 'liberacao:'.Str::uuid7(), $this->operador(), 'Teste do relé');

        config(['integracoes.teste_rele_habilitado' => false]);
        $processada = $this->processar($operacao);

        $this->assertSame(ResultadoEquipamento::Recusado, $processada->resultado);
        $this->assertSame(0, $this->simulador()->totalAberturasFisicas($equipamento->id));
    }

    public function test_bancada_nao_e_ativada_como_ponto_real(): void
    {
        $equipamento = $this->terminalEmHomologacao();

        $this->assertRegraViolada('ponto_bancada', fn () => $this->cadastro()->alterarStatusPonto($equipamento->pontoVigente(), StatusPontoAcesso::Ativo, 'teste'));
        $this->assertRegraViolada('ponto_bancada', fn () => $this->cadastro()->alterarStatus($equipamento, StatusEquipamento::Ativo, 'teste'));
    }

    public function test_abertura_operacional_continua_desligada_mesmo_com_teste_de_rele_ligado(): void
    {
        config(['integracoes.abertura_remota_habilitada' => false]);
        $terminal = $this->terminalAtivo();

        $this->assertRegraViolada('abertura_remota_desabilitada', fn () => app(ComandosAbertura::class)->solicitar(
            $terminal->pontoVigente(), (string) Str::uuid7(), 'abertura:'.Str::uuid7(), $this->operador(), 'motivo',
        ));
    }
}
