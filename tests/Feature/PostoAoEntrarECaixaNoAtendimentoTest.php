<?php

namespace Tests\Feature;

use App\Integracoes\Aplicacao\CadastroPostos;
use App\Integracoes\Aplicacao\TurnosOperacionais;
use App\Livewire\AccessValidation;
use App\Livewire\AvisoCaixaAtendimento;
use App\Livewire\CashRegister;
use App\Livewire\Login;
use App\Livewire\SelecaoPosto;
use App\Models\AuditoriaEvento;
use App\Models\CaixaTurno;
use App\Models\OperadorTurno;
use App\Models\Posto;
use App\Models\User;
use App\Models\UsuarioImplantacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\TestCase;

/**
 * Regras do PO de 26/11/2026: quem opera posto confirma ou escolhe o posto
 * logo ao entrar; se o sistema caiu com o caixa aberto, posto e caixa ficam
 * mantidos; turno antigo com caixa fechado é encerrado no novo login; o
 * atendimento avisa o caixa fechado logo ao abrir e deixa abrir ali mesmo.
 */
class PostoAoEntrarECaixaNoAtendimentoTest extends TestCase
{
    use MontaCenarioIntegracao, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararIntegracao();
    }

    protected function tearDown(): void
    {
        $this->encerrarIntegracao();
        parent::tearDown();
    }

    private function porteiro(string $login = 'porteiro.teste'): User
    {
        $usuario = $this->usuarioCom('validacao.registrar', 'caixa.proprio.gerenciar');
        $usuario->forceFill(['username' => $login, 'password' => 'senha-correta', 'status' => 'ativo'])->save();
        UsuarioImplantacao::query()->create(['user_id' => $usuario->id, 'status' => 'ativa']);

        return $usuario;
    }

    private function posto(string $nome): Posto
    {
        return app(CadastroPostos::class)->criarPosto('P-'.strtoupper(substr(md5($nome), 0, 6)), $nome, null, Posto::TIPO_PRINCIPAL);
    }

    private function entrar(string $login = 'porteiro.teste'): mixed
    {
        return Livewire::test(Login::class)
            ->set('identification', $login)
            ->set('password', 'senha-correta')
            ->call('login');
    }

    public function test_ao_entrar_sem_posto_e_levado_a_escolher(): void
    {
        $this->posto('Guarita Principal');
        $this->porteiro();

        $this->entrar()->assertRedirect(route('posto.selecionar', ['voltar' => '/inicio']));
    }

    public function test_caiu_com_caixa_aberto_mantem_posto_e_caixa_e_so_confirma(): void
    {
        $posto = $this->posto('Acesso Pedestres');
        $porteiro = $this->porteiro();
        app(TurnosOperacionais::class)->assumir($porteiro, $posto->id);
        app(TurnosOperacionais::class)->abrirCaixa($porteiro, 150.0);

        $this->entrar()->assertRedirect(route('posto.selecionar', ['voltar' => '/inicio']));

        $this->assertNotNull(app(TurnosOperacionais::class)->turnoAtivo($porteiro), 'O posto foi mantido.');
        $this->assertSame(1, CaixaTurno::query()->where('operator_id', $porteiro->id)->where('status', 'aberto')->count());

        Livewire::actingAs($porteiro)->withQueryParams(['voltar' => '/inicio'])
            ->test(SelecaoPosto::class)
            ->assertSee('Você estava no posto Acesso Pedestres')
            ->assertSee('Continuar no posto Acesso Pedestres')
            ->call('continuar')
            ->assertRedirect('/inicio');
    }

    public function test_turno_antigo_com_caixa_fechado_e_encerrado_no_novo_login(): void
    {
        $posto = $this->posto('Acesso Pedestres');
        $porteiro = $this->porteiro();
        app(TurnosOperacionais::class)->assumir($porteiro, $posto->id);

        $this->entrar()->assertRedirect(route('posto.selecionar', ['voltar' => '/inicio']));

        $turno = OperadorTurno::query()->where('user_id', $porteiro->id)->sole();
        $this->assertNotNull($turno->encerrado_em, 'O posto antigo ficou livre.');
        $this->assertSame(TurnosOperacionais::MOTIVO_SESSAO_ANTERIOR, $turno->motivo_encerramento);
        $this->assertTrue(AuditoriaEvento::query()->where('action', 'posto_operacional_encerrado')->exists());
    }

    public function test_quem_nao_opera_posto_vai_direto_para_o_inicio(): void
    {
        $this->posto('Guarita Principal');
        $usuario = $this->usuarioCom('relatorios.proprio.consultar');
        $usuario->forceFill(['username' => 'consulta.teste', 'password' => 'senha-correta', 'status' => 'ativo'])->save();
        UsuarioImplantacao::query()->create(['user_id' => $usuario->id, 'status' => 'ativa']);

        $this->entrar('consulta.teste')->assertRedirect(route('home'));
    }

    public function test_atendimento_avisa_caixa_fechado_e_abre_ali_mesmo(): void
    {
        $posto = $this->posto('Guarita Principal');
        $porteiro = $this->porteiro();
        app(TurnosOperacionais::class)->assumir($porteiro, $posto->id);

        Livewire::actingAs($porteiro)->test(AvisoCaixaAtendimento::class)
            ->assertSee('Seu caixa está fechado')
            ->assertSee('Abrir caixa')
            ->set('saldoInicial', '120,00')
            ->call('abrir')
            ->assertHasNoErrors()
            ->assertDispatched('caixa-aberto')
            ->assertSee('Caixa aberto');

        $caixa = CaixaTurno::query()->where('operator_id', $porteiro->id)->where('status', 'aberto')->sole();
        $this->assertSame($posto->id, $caixa->posto_id);
        $this->assertEquals(120.0, (float) $caixa->opening_balance);

        Livewire::actingAs($porteiro)->test(AvisoCaixaAtendimento::class)->assertDontSee('Seu caixa está fechado');
    }

    public function test_aviso_pode_so_consultar_sem_abrir(): void
    {
        $this->posto('Guarita Principal');
        $porteiro = $this->porteiro();

        Livewire::actingAs($porteiro)->test(AvisoCaixaAtendimento::class)
            ->assertSee('Posto deste caixa')
            ->call('dispensar')
            ->assertDontSee('Seu caixa está fechado');

        $this->assertSame(0, CaixaTurno::query()->count());
    }

    public function test_abrir_caixa_pergunta_o_posto_e_troca_para_o_escolhido(): void
    {
        $principal = $this->posto('Guarita Principal');
        $pedestres = $this->posto('Acesso Pedestres');
        $outro = $this->porteiro('outro.porteiro');
        $veiculos = $this->posto('Guarita Veiculos');
        app(TurnosOperacionais::class)->assumir($outro, $veiculos->id);
        $porteiro = $this->porteiro();
        app(TurnosOperacionais::class)->assumir($porteiro, $principal->id);

        // Começa no posto atual; posto ocupado por outro operador não aparece.
        Livewire::actingAs($porteiro)->test(CashRegister::class)
            ->assertSet('postoAbertura', $principal->id)
            ->assertSee('Acesso Pedestres')
            ->assertDontSee('Guarita Veiculos')
            ->set('postoAbertura', $pedestres->id)
            ->set('openingBalanceInput', '50,00')
            ->call('openRegister')
            ->assertSet('status', 'aberto');

        $caixa = CaixaTurno::query()->where('operator_id', $porteiro->id)->sole();
        $this->assertSame($pedestres->id, $caixa->posto_id);
        $this->assertSame('Acesso Pedestres', $caixa->terminal);
        $this->assertSame($pedestres->id, app(TurnosOperacionais::class)->turnoAtivo($porteiro)?->posto_id, 'O turno passou para o posto escolhido.');
        $this->assertTrue(AuditoriaEvento::query()->where('action', 'posto_operacional_trocado')->exists());
    }

    public function test_aviso_do_atendimento_abre_sem_posto_escolhendo_ali(): void
    {
        $pedestres = $this->posto('Acesso Pedestres');
        $porteiro = $this->porteiro();

        Livewire::actingAs($porteiro)->test(AvisoCaixaAtendimento::class)
            ->set('postoAbertura', $pedestres->id)
            ->call('abrir')
            ->assertHasNoErrors()
            ->assertSee('registrado no posto Acesso Pedestres');

        $this->assertSame($pedestres->id, CaixaTurno::query()->sole()->posto_id);
    }

    public function test_cabecalho_e_atendimento_mostram_o_posto_real(): void
    {
        $pedestres = $this->posto('Acesso Pedestres');
        $porteiro = $this->porteiro();
        app(TurnosOperacionais::class)->assumir($porteiro, $pedestres->id);

        $this->actingAs($porteiro)->get(route('portaria'))->assertOk()
            ->assertSee('Operador de portaria · Acesso Pedestres')
            ->assertDontSee('Operador de portaria · Portaria Principal');
        Livewire::actingAs($porteiro)->test(AccessValidation::class)->assertSet('pontoAcesso', 'Acesso Pedestres');
    }

    public function test_tela_de_atendimento_mostra_o_aviso(): void
    {
        $posto = $this->posto('Guarita Principal');
        $porteiro = $this->porteiro();
        app(TurnosOperacionais::class)->assumir($porteiro, $posto->id);

        $this->actingAs($porteiro)->get(route('validation'))->assertOk()->assertSee('Seu caixa está fechado');
        Livewire::actingAs($porteiro)->test(AccessValidation::class)->dispatch('caixa-aberto')->assertOk();
    }
}
