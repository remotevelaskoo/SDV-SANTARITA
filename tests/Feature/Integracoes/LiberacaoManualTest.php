<?php

namespace Tests\Feature\Integracoes;

use App\Integracoes\Adaptadores\Simulador\CenarioSimulador;
use App\Integracoes\Aplicacao\CadastroPostos;
use App\Integracoes\Aplicacao\ComandosAbertura;
use App\Integracoes\Aplicacao\DiagnosticoEquipamento;
use App\Integracoes\Aplicacao\LiberacaoManual;
use App\Integracoes\Aplicacao\ProcessadorOperacoes;
use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\Direcao;
use App\Integracoes\Dominio\Enums\EstadoSaude;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Integracoes\Dominio\Enums\StatusPontoAcesso;
use App\Integracoes\Dominio\Excecoes\PayloadDivergente;
use App\Livewire\LiberacaoManualPortaria;
use App\Models\AuditoriaEvento;
use App\Models\Equipamento;
use App\Models\HistoricoAcesso;
use App\Models\Implantacao;
use App\Models\Pessoa;
use App\Models\Posto;
use App\Models\User;
use App\Models\UsuarioImplantacao;
use App\Models\Vinculo;
use App\Support\ImplantacaoContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\TestCase;

/**
 * Liberação manual operacional no Modo Portaria (ADR-017).
 */
class LiberacaoManualTest extends TestCase
{
    use MontaCenarioIntegracao, RefreshDatabase;

    private const SENHA = 'Senha-Tecnica-Operacao-999';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararIntegracao();
        config(['integracoes.liberacao_operacional_habilitada' => true]);
    }

    protected function tearDown(): void
    {
        $this->encerrarIntegracao();
        parent::tearDown();
    }

    // ---- Cenário ---------------------------------------------------------------

    /** Terminal ativo em ponto real, online, homologado e com relé homologado. */
    private function terminalOperacional(string $nome, string $direcaoEquipamento = 'entrada', string $direcaoPonto = 'bidirecional'): Equipamento
    {
        $ponto = $this->novoPonto(['nome' => 'Ponto '.$nome, 'direcao_suportada' => $direcaoPonto]);
        $this->cadastro()->alterarStatusPonto($ponto, StatusPontoAcesso::Ativo, 'operação');
        $equipamento = $this->novoEquipamento(['nome' => $nome, 'direcao' => $direcaoEquipamento]);
        $this->cadastro()->vincularAoPonto($equipamento, $ponto);
        $this->cadastro()->definirSenhaTecnica($equipamento, 'admin', self::SENHA);
        $this->cadastro()->confiarCertificado($equipamento, str_repeat('B', 43).'=', 'IMPRESSAO-SINTETICA');
        $this->processar(app(DiagnosticoEquipamento::class)->consultarCapacidades($equipamento->refresh()));
        $this->processar(app(DiagnosticoEquipamento::class)->testarConexao($equipamento->refresh()));
        $equipamento->refresh()->forceFill(['rele_homologado_em' => now()])->save();

        return $this->cadastro()->alterarStatus($equipamento->refresh(), StatusEquipamento::Ativo, 'operação');
    }

    private function operador(string ...$extras): User
    {
        $usuario = $this->usuarioCom('validacao.registrar', ComandosAbertura::PERMISSAO_LIBERACAO_MANUAL, ...$extras);
        UsuarioImplantacao::query()->create(['user_id' => $usuario->id, 'status' => 'ativa']);

        return $usuario;
    }

    private function posto(Equipamento $equipamento, User $operador, string $codigo = 'GUARITA-01'): Posto
    {
        $cadastro = app(CadastroPostos::class);
        $posto = $cadastro->criarPosto($codigo, 'Guarita '.$codigo);
        $cadastro->vincularPonto($posto, $equipamento->pontoVigente());
        $cadastro->designarOperador($posto, $operador);

        return $posto;
    }

    private function pessoaAutorizada(): Pessoa
    {
        return Vinculo::factory()->create()->pessoa;
    }

    private function liberar(User $operador, Posto $posto, Equipamento $equipamento, array $extra = [], ?string $chave = null): HistoricoAcesso
    {
        $evento = app(LiberacaoManual::class)->solicitar($operador, array_merge([
            'posto_id' => $posto->id,
            'equipamento_id' => $equipamento->id,
            'direcao' => 'entrada',
            'pessoa_id' => $this->pessoaAutorizada()->id,
            'motivo' => 'Morador sem credencial no momento',
        ], $extra), $chave ?? 'liberacao-manual:'.Str::uuid7());

        $this->processar($evento->operacaoIntegracao);

        return $evento->refresh();
    }

    // ---- Fluxo principal ---------------------------------------------------------

    public function test_operador_libera_equipamento_autorizado_do_seu_posto_e_gera_evento_de_acesso(): void
    {
        $equipamento = $this->terminalOperacional('Facial Entrada Principal');
        $operador = $this->operador();
        $posto = $this->posto($equipamento, $operador);
        $this->simulador()->definirCenario($equipamento->id, CenarioSimulador::Aceito);
        $this->actingAs($operador);
        $pessoa = $this->pessoaAutorizada();
        $chave = 'liberacao-manual:'.Str::uuid7();

        $evento = $this->liberar($operador, $posto, $equipamento, ['pessoa_id' => $pessoa->id], $chave);

        $this->assertSame(1, $this->simulador()->totalAberturasFisicas($equipamento->id));
        $this->assertSame('liberado', $evento->resultado);
        $this->assertSame('aceita', $evento->resultado_tecnico);
        $this->assertSame('liberacao_manual', $evento->origem);
        $this->assertSame($pessoa->id, $evento->pessoa_id);
        $this->assertNotNull($evento->imovel_id);
        $this->assertSame($operador->id, $evento->operator_id);
        $this->assertSame($posto->id, $evento->posto_id);
        $this->assertSame($equipamento->id, $evento->equipamento_id);
        $this->assertSame($equipamento->pontoVigente()->id, $evento->ponto_acesso_id);
        $this->assertSame('entrada', $evento->tipo);
        $this->assertSame('Morador sem credencial no momento', $evento->motivo);
        $this->assertSame($chave, $evento->chave_idempotencia);
        $this->assertSame($evento->operacaoIntegracao->correlation_id, $evento->correlation_id);
        $this->assertFalse($evento->excepcional);

        $auditoria = AuditoriaEvento::query()->where('action', 'liberacao_manual_solicitada')->with('context')->sole();
        $this->assertSame($operador->id, $auditoria->actor_id);
        $this->assertSame($this->implantacao->id, $auditoria->implantacao_id);
        foreach (['posto_id', 'equipamento_id', 'ponto_acesso_id', 'direcao', 'pessoa_id', 'correlation_id', 'chave_idempotencia', 'protocolo'] as $campo) {
            $this->assertArrayHasKey($campo, $auditoria->context->metadata, $campo);
        }
        $this->assertSame('liberacao_manual', $auditoria->context->metadata['origem']);
    }

    public function test_um_unico_equipamento_compativel_e_listado_com_nome_amigavel(): void
    {
        $entrada = $this->terminalOperacional('Facial Entrada Principal', 'entrada');
        $saida = $this->terminalOperacional('Facial Saída Principal', 'saida');
        $operador = $this->operador();
        $posto = $this->posto($entrada, $operador);
        app(CadastroPostos::class)->vincularPonto($posto, $saida->pontoVigente());

        $paraEntrada = app(LiberacaoManual::class)->equipamentosPermitidos($operador, $posto, Direcao::Entrada);
        $paraSaida = app(LiberacaoManual::class)->equipamentosPermitidos($operador, $posto, Direcao::Saida);

        $this->assertSame(['Facial Entrada Principal'], array_map(fn ($i) => $i['equipamento']->nome, $paraEntrada));
        $this->assertSame(['Facial Saída Principal'], array_map(fn ($i) => $i['equipamento']->nome, $paraSaida));
    }

    // ---- Isolamento ------------------------------------------------------------

    public function test_operador_nao_ve_nem_aciona_equipamento_de_outro_posto(): void
    {
        $meu = $this->terminalOperacional('Facial Entrada Principal');
        $outro = $this->terminalOperacional('Facial Acesso Praia');
        $operador = $this->operador();
        $meuPosto = $this->posto($meu, $operador, 'GUARITA-01');
        $outroPosto = $this->posto($outro, $this->operador(), 'GUARITA-PRAIA');

        $visiveis = app(LiberacaoManual::class)->equipamentosPermitidos($operador, $meuPosto, Direcao::Entrada);
        $this->assertSame([$meu->id], array_map(fn ($i) => $i['equipamento']->id, $visiveis));
        $this->assertSame([], app(LiberacaoManual::class)->equipamentosPermitidos($operador, $outroPosto, Direcao::Entrada));

        // Equipamento de outro posto informado à força pelo navegador.
        $this->assertRegraViolada('ponto_fora_do_posto', fn () => $this->liberar($operador, $meuPosto, $outro));
        $this->assertRegraViolada('posto_nao_autorizado', fn () => $this->liberar($operador, $outroPosto, $outro));
        $this->assertSame(0, $this->simulador()->totalAberturasFisicas($outro->id));
    }

    public function test_operador_nao_acessa_equipamento_de_outra_implantacao(): void
    {
        $equipamento = $this->terminalOperacional('Facial Entrada Principal');
        $posto = $this->posto($equipamento, $this->operador());

        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        $intruso = $this->operador();

        try {
            $this->liberar($intruso, $posto, $equipamento);
            $this->fail('Equipamento de outra implantação foi acionado.');
        } catch (ModelNotFoundException) {
            $this->assertSame(0, $this->simulador()->totalAberturasFisicas($equipamento->id));
        } finally {
            ImplantacaoContext::setCurrentForTesting($this->implantacao);
        }
    }

    // ---- Requisitos ------------------------------------------------------------

    public function test_usuario_sem_permissao_e_teste_de_rele_nao_servem_para_liberar(): void
    {
        $equipamento = $this->terminalOperacional('Facial Entrada Principal');
        $semPermissao = $this->usuarioCom('validacao.registrar', ComandosAbertura::PERMISSAO_LIBERAR);
        UsuarioImplantacao::query()->create(['user_id' => $semPermissao->id, 'status' => 'ativa']);
        $posto = $this->posto($equipamento, $semPermissao);

        $this->assertRegraViolada('sem_permissao', fn () => $this->liberar($semPermissao, $posto, $equipamento));
    }

    public function test_bloqueios_do_equipamento(): void
    {
        $casos = [
            'equipamento_offline' => fn (Equipamento $e) => $e->forceFill(['estado_saude' => EstadoSaude::Indisponivel])->save(),
            'equipamento_nao_operacional' => fn (Equipamento $e) => $this->cadastro()->alterarStatus($e, StatusEquipamento::Manutencao, 'manutenção'),
            'certificado_nao_confiavel' => fn (Equipamento $e) => $e->forceFill(['tls_pin_sha256' => null])->save(),
            'firmware_nao_homologado' => fn (Equipamento $e) => $e->capacidades()->where('capacidade', Capacidade::AberturaRemota->value)->update(['estado_homologacao' => 'em_homologacao']),
            'rele_nao_homologado' => fn (Equipamento $e) => $e->forceFill(['rele_homologado_em' => null])->save(),
        ];

        foreach ($casos as $codigo => $preparar) {
            $equipamento = $this->terminalOperacional('Facial '.$codigo);
            $operador = $this->operador();
            $posto = $this->posto($equipamento, $operador, 'P-'.Str::upper(Str::random(6)));
            $preparar($equipamento);

            $this->assertRegraViolada($codigo, fn () => $this->liberar($operador, $posto, $equipamento->refresh()));
            $this->assertSame([], app(LiberacaoManual::class)->equipamentosPermitidos($operador, $posto, Direcao::Entrada), $codigo);
            $this->assertSame(0, $this->simulador()->totalAberturasFisicas($equipamento->id), $codigo);
        }
    }

    public function test_direcao_incompativel_e_flag_operacional_desligada(): void
    {
        $equipamento = $this->terminalOperacional('Facial Entrada Principal', 'entrada');
        $operador = $this->operador();
        $posto = $this->posto($equipamento, $operador);

        $this->assertRegraViolada('direcao_incompativel', fn () => $this->liberar($operador, $posto, $equipamento, ['direcao' => 'saida']));

        config(['integracoes.liberacao_operacional_habilitada' => false]);
        $this->assertRegraViolada('liberacao_operacional_desabilitada', fn () => $this->liberar($operador, $posto, $equipamento));
        $this->assertSame(0, $this->simulador()->totalAberturasFisicas($equipamento->id));
    }

    public function test_pessoa_sem_vinculo_so_por_liberacao_excepcional(): void
    {
        $equipamento = $this->terminalOperacional('Facial Entrada Principal');
        $operador = $this->operador();
        $posto = $this->posto($equipamento, $operador);
        $semVinculo = Pessoa::factory()->create();

        $this->assertRegraViolada('sem_vinculo_vigente', fn () => $this->liberar($operador, $posto, $equipamento, ['pessoa_id' => $semVinculo->id]));
        $this->assertRegraViolada('sem_permissao_excepcional', fn () => $this->liberar($operador, $posto, $equipamento, [
            'excepcional' => true, 'identificacao' => 'Técnico da concessionária', 'justificativa' => 'Reparo emergencial na rede elétrica do condomínio',
        ]));

        $autorizado = $this->operador(ComandosAbertura::PERMISSAO_LIBERACAO_EXCEPCIONAL);
        app(CadastroPostos::class)->designarOperador($posto, $autorizado);
        $this->assertRegraViolada('sem_justificativa', fn () => $this->liberar($autorizado, $posto, $equipamento, [
            'excepcional' => true, 'identificacao' => 'Técnico', 'justificativa' => 'curta',
        ]));

        $evento = $this->liberar($autorizado, $posto, $equipamento, [
            'excepcional' => true, 'identificacao' => 'Técnico da concessionária', 'justificativa' => 'Reparo emergencial na rede elétrica do condomínio',
        ]);

        $this->assertTrue($evento->excepcional);
        $this->assertNull($evento->pessoa_id);
        $this->assertSame('Técnico da concessionária', $evento->identificacao_excepcional);
        $this->assertTrue(AuditoriaEvento::query()->where('action', 'liberacao_manual_excepcional_solicitada')->where('classification', 'restrita')->exists());
    }

    // ---- Idempotência e resultados ---------------------------------------------

    public function test_clique_duplo_e_idempotencia(): void
    {
        $equipamento = $this->terminalOperacional('Facial Entrada Principal');
        $operador = $this->operador();
        $posto = $this->posto($equipamento, $operador);
        $pessoa = $this->pessoaAutorizada();
        $chave = 'liberacao-manual:'.Str::uuid7();

        $primeira = $this->liberar($operador, $posto, $equipamento, ['pessoa_id' => $pessoa->id], $chave);
        $segunda = $this->liberar($operador, $posto, $equipamento, ['pessoa_id' => $pessoa->id], $chave);

        $this->assertSame($primeira->id, $segunda->id);
        $this->assertSame(1, HistoricoAcesso::query()->where('origem', 'liberacao_manual')->count());
        $this->assertSame(1, $this->simulador()->totalAberturasFisicas($equipamento->id));

        $this->expectException(PayloadDivergente::class);
        $this->liberar($operador, $posto, $equipamento, ['pessoa_id' => $pessoa->id, 'direcao' => 'saida'], $chave);
    }

    public function test_timeout_gera_confirmacao_desconhecida_sem_reenvio(): void
    {
        $equipamento = $this->terminalOperacional('Facial Entrada Principal');
        $operador = $this->operador();
        $posto = $this->posto($equipamento, $operador);
        $this->simulador()->definirCenario($equipamento->id, CenarioSimulador::Timeout);

        $evento = $this->liberar($operador, $posto, $equipamento);

        $this->assertSame('desconhecida', $evento->resultado_tecnico);
        $this->assertSame(1, $evento->operacaoIntegracao->max_tentativas);
        $this->assertNull(app(ProcessadorOperacoes::class)->processar($evento->operacao_integracao_id));
    }

    // ---- Tela -----------------------------------------------------------------

    public function test_tela_mostra_confirmacao_e_nao_apresenta_aceite_como_passagem_confirmada(): void
    {
        $equipamento = $this->terminalOperacional('Facial Entrada Principal');
        $operador = $this->operador();
        $posto = $this->posto($equipamento, $operador);
        $pessoa = $this->pessoaAutorizada();
        $this->simulador()->definirCenario($equipamento->id, CenarioSimulador::Aceito);

        Livewire::actingAs($operador)->test(LiberacaoManualPortaria::class)
            ->assertSet('postoId', $posto->id)
            ->call('selecionarPessoa', $pessoa->id)
            ->assertSee($pessoa->nomeExibicao())
            ->assertSee('Facial Entrada Principal')
            ->set('motivo', 'Morador sem credencial no momento')
            ->call('revisar')
            ->assertSee('Você está prestes a acionar Facial Entrada Principal, no ponto Ponto Facial Entrada Principal. Confirma a liberação?')
            ->call('confirmar')
            ->assertSee('Aceita pelo terminal')
            ->assertSee('não confirma a passagem física')
            ->assertSee('SRA-')
            ->assertDontSee(self::SENHA)
            ->assertDontSee($equipamento->endereco_rede);

        $this->assertSame(1, $this->simulador()->totalAberturasFisicas($equipamento->id));
    }

    public function test_tela_sem_permissao_ou_com_flag_desligada_nao_oferece_liberacao(): void
    {
        $equipamento = $this->terminalOperacional('Facial Entrada Principal');
        $operador = $this->operador();
        $this->posto($equipamento, $operador);

        config(['integracoes.liberacao_operacional_habilitada' => false]);
        Livewire::actingAs($operador)->test(LiberacaoManualPortaria::class)
            ->assertSee('Liberação manual desligada')
            ->assertDontSee('Revisar liberação');

        $this->actingAs($this->usuarioCom('validacao.registrar'))->get('/portaria')->assertOk()->assertDontSee('Liberação manual');
    }

    public function test_tela_de_postos_exige_permissao_e_designacao_e_auditada(): void
    {
        $this->actingAs($this->usuarioCom('validacao.registrar'))->get('/postos')->assertRedirect(route('dashboard'));

        $admin = $this->usuarioCom('integracoes.gerenciar');
        $this->actingAs($admin)->get('/postos')->assertOk()->assertSee('Postos e operadores');

        $equipamento = $this->terminalOperacional('Facial Prestadores');
        $operador = $this->operador();
        $posto = $this->posto($equipamento, $operador);
        app(CadastroPostos::class)->encerrarDesignacao($posto, $operador);

        $this->assertSame([], app(LiberacaoManual::class)->equipamentosPermitidos($operador, $posto, Direcao::Entrada));
        $this->assertTrue(AuditoriaEvento::query()->where('action', 'operador_removido_do_posto')->exists());
    }

    public function test_nenhum_segredo_em_log_auditoria_ou_evento(): void
    {
        Log::spy();
        $equipamento = $this->terminalOperacional('Facial Entrada Principal');
        $operador = $this->operador();
        $posto = $this->posto($equipamento, $operador);

        $this->liberar($operador, $posto, $equipamento);

        $tudo = json_encode([
            DB::table('auditoria_eventos')->get(), DB::table('auditoria_alteracoes')->get(), DB::table('auditoria_contextos')->get(),
            DB::table('historico_acessos')->get(), DB::table('operacoes_integracao')->get(), DB::table('integracao_ocorrencias')->get(),
        ]);
        $this->assertStringNotContainsString(self::SENHA, $tudo);
        $this->assertStringNotContainsString($equipamento->endereco_rede, json_encode(DB::table('historico_acessos')->get()));
        Log::shouldNotHaveReceived('info', fn ($m, $ctx = []) => str_contains(json_encode($ctx), self::SENHA));
    }
}
