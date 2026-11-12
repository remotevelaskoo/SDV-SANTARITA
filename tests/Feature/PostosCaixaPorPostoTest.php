<?php

namespace Tests\Feature;

use App\Integracoes\Aplicacao\CadastroPostos;
use App\Integracoes\Aplicacao\TurnosOperacionais;
use App\Livewire\CashRegister;
use App\Livewire\Portaria;
use App\Livewire\PostoManagement;
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
 * Regras do PO de 12/11/2026 (SDV-OPE-018 §14): qualquer operador em qualquer
 * posto, um operador por posto, um caixa por posto, saída bloqueada com o
 * caixa aberto e liberação de posto preso por Gestor/Administrador.
 */
class PostosCaixaPorPostoTest extends TestCase
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

    private function porteiro(): User
    {
        $usuario = $this->usuarioCom('validacao.registrar', 'caixa.proprio.gerenciar');
        UsuarioImplantacao::query()->create(['user_id' => $usuario->id, 'status' => 'ativa']);

        return $usuario;
    }

    private function posto(string $nome, string $tipo = Posto::TIPO_PRINCIPAL): Posto
    {
        return app(CadastroPostos::class)->criarPosto('P-'.strtoupper(substr(md5($nome), 0, 6)), $nome, null, $tipo);
    }

    private function turnos(): TurnosOperacionais
    {
        return app(TurnosOperacionais::class);
    }

    private function abrirCaixa(User $operador, string $saldo = '200,00'): void
    {
        Livewire::actingAs($operador)->test(CashRegister::class)
            ->set('openingBalanceInput', $saldo)
            ->call('openRegister')
            ->assertSet('status', 'aberto');
    }

    public function test_caixa_so_abre_com_posto_e_fica_no_posto_do_turno(): void
    {
        $tatiane = $this->porteiro();
        $principal = $this->posto('Guarita Principal');

        Livewire::actingAs($tatiane)->test(CashRegister::class)
            ->assertSee('Escolher o posto')
            ->call('openRegister')
            ->assertSet('status', 'fechado')
            ->assertSet('feedback.title', 'Escolha o posto');
        $this->assertSame(0, CaixaTurno::query()->count());

        $this->turnos()->assumir($tatiane, $principal->id);
        $this->abrirCaixa($tatiane);

        $caixa = CaixaTurno::query()->sole();
        $this->assertSame($principal->id, $caixa->posto_id);
        $this->assertSame('Guarita Principal', $caixa->terminal);
        $this->assertSame($tatiane->id, $caixa->operator_id);
    }

    public function test_cada_posto_tem_o_seu_caixa_e_cada_operador_ve_o_seu(): void
    {
        $tatiane = $this->porteiro();
        $renata = $this->porteiro();
        $this->turnos()->assumir($tatiane, $this->posto('Guarita Principal')->id);
        $this->turnos()->assumir($renata, $this->posto('Guarita Veículos', Posto::TIPO_VEICULOS)->id);

        $this->abrirCaixa($tatiane, '200,00');
        $this->abrirCaixa($renata, '50,00');

        $this->assertSame(2, CaixaTurno::query()->where('status', 'aberto')->count());
        Livewire::actingAs($renata)->test(CashRegister::class)
            ->assertSet('terminal', 'Guarita Veículos')
            ->assertSet('openingBalance', 50.0);
        Livewire::actingAs($tatiane)->test(Portaria::class)
            ->assertSee('Guarita Principal')
            ->assertDontSee('Guarita Veículos · ');
    }

    public function test_com_caixa_aberto_nao_troca_nao_encerra_e_nao_sai(): void
    {
        $tatiane = $this->porteiro();
        $principal = $this->posto('Guarita Principal');
        $pedestres = $this->posto('Guarita Pedestres', Posto::TIPO_PEDESTRES);
        $this->turnos()->assumir($tatiane, $principal->id);
        $this->abrirCaixa($tatiane);

        $this->assertRegraViolada('caixa_aberto', fn () => $this->turnos()->assumir($tatiane, $pedestres->id));
        $this->assertRegraViolada('caixa_aberto', fn () => $this->turnos()->encerrar($tatiane));
        Livewire::actingAs($tatiane)->test(SelecaoPosto::class)
            ->assertSee('Seu caixa está aberto')
            ->assertSee('Ir para o caixa e fechar')
            ->assertDontSee('Trocar para este posto');

        $this->actingAs($tatiane)->from('/portaria')->post('/sair')
            ->assertRedirect('/portaria')
            ->assertSessionHas('erro')
            ->assertSessionHas('erro_titulo', 'Caixa aberto');
        $this->assertAuthenticatedAs($tatiane);
        $this->assertNotNull($this->turnos()->turnoAtivo($tatiane));

        Livewire::actingAs($tatiane)->test(CashRegister::class)
            ->set('informedAmount', '200,00')
            ->call('closeRegister');

        $this->actingAs($tatiane)->post('/sair')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame(TurnosOperacionais::MOTIVO_SAIDA, OperadorTurno::query()->where('user_id', $tatiane->id)->value('motivo_encerramento'));

        $renata = $this->porteiro();
        $this->turnos()->assumir($renata, $principal->id);
        $this->assertSame($principal->id, $this->turnos()->turnoAtivo($renata)->posto_id, 'Depois de fechar o caixa e sair, o posto fica livre.');
    }

    public function test_gestor_libera_posto_preso_e_caixa_vai_para_conferencia(): void
    {
        $tatiane = $this->porteiro();
        $principal = $this->posto('Guarita Principal');
        $this->turnos()->assumir($tatiane, $principal->id);
        $this->abrirCaixa($tatiane, '200,00');

        $porteiro = $this->porteiro();
        $this->assertRegraViolada('sem_permissao', fn () => $this->turnos()->liberar($principal, $porteiro, 'computador travou'));
        $this->actingAs($porteiro)->get('/postos')->assertRedirect(route('home'));

        $gestor = $this->usuarioCom(TurnosOperacionais::PERMISSAO_LIBERAR);
        $this->assertRegraViolada('motivo_obrigatorio', fn () => $this->turnos()->liberar($principal, $gestor, 'x'));

        Livewire::actingAs($gestor)->test(PostoManagement::class)
            ->assertSee('Ocupado por '.$tatiane->name)
            ->assertDontSee('+ Novo posto')
            ->call('iniciarLiberacao', $principal->id)
            ->set('motivoLiberacao', 'Computador da guarita travou')
            ->call('liberar')
            ->assertSet('feedback.title', 'Posto liberado')
            ->assertSee('Livre');

        $caixa = CaixaTurno::query()->sole();
        $this->assertSame('fechado', $caixa->status);
        $this->assertSame(TurnosOperacionais::CAIXA_AGUARDANDO_CONFERENCIA, $caixa->closing_status);
        $this->assertNull($caixa->informed_amount);
        $this->assertSame('200.00', (string) $caixa->expected_amount);
        $this->assertStringContainsString('Computador da guarita travou', $caixa->closing_notes);
        $this->assertNull($this->turnos()->turnoAtivo($tatiane));
        $this->assertSame(TurnosOperacionais::MOTIVO_LIBERADO, OperadorTurno::query()->where('user_id', $tatiane->id)->value('motivo_encerramento'));
        $this->assertTrue(AuditoriaEvento::query()->where('action', 'posto_operacional_liberado')->exists());

        $this->assertRegraViolada('posto_livre', fn () => $this->turnos()->liberar($principal, $gestor, 'de novo, por engano'));

        $renata = $this->porteiro();
        $this->turnos()->assumir($renata, $principal->id);
        $this->abrirCaixa($renata);
        $this->assertSame(1, CaixaTurno::query()->where('status', 'aberto')->where('posto_id', $principal->id)->count());
    }

    public function test_supervisor_pode_seguir_sem_posto_e_porteiro_e_levado_a_escolher(): void
    {
        $this->posto('Guarita Principal');
        $porteiro = $this->porteiro();
        $gestor = $this->usuarioCom('validacao.registrar', 'caixa.proprio.gerenciar', 'caixa.consolidado.consultar');
        UsuarioImplantacao::query()->create(['user_id' => $gestor->id, 'status' => 'ativa']);

        $this->actingAs($porteiro)->get('/portaria')->assertRedirect(route('posto.selecionar', ['voltar' => '/portaria']));
        Livewire::actingAs($porteiro)->test(SelecaoPosto::class)->assertDontSee('Continuar sem assumir posto');

        $this->actingAs($gestor)->get('/portaria')->assertRedirect(route('posto.selecionar', ['voltar' => '/portaria']));
        Livewire::actingAs($gestor)->test(SelecaoPosto::class, ['voltar' => '/portaria'])
            ->call('seguirSemPosto')
            ->assertRedirect('/portaria');
        $this->actingAs($gestor)->withSession([SelecaoPosto::SESSAO_SEM_POSTO => true])->get('/portaria')->assertOk();
        $this->assertSame(0, OperadorTurno::query()->count(), 'Supervisor não ocupou nenhum posto.');
    }
}
