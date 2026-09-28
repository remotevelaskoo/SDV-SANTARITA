<?php

namespace Tests\Feature\Integracoes;

use App\Integracoes\Adaptadores\Simulador\CenarioSimulador;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Livewire\EquipamentoManagement;
use App\Models\Equipamento;
use App\Models\EquipamentoCredencial;
use App\Models\Implantacao;
use App\Support\ImplantacaoContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\TestCase;

class TelaEquipamentosTest extends TestCase
{
    use MontaCenarioIntegracao, RefreshDatabase;

    private const SENHA = 'Senha-Digitada-Na-Tela-555';

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

    public function test_rota_exige_autenticacao_e_permissao(): void
    {
        $this->get('/equipamentos')->assertRedirect(route('login'));
        $this->actingAs($this->usuarioCom('dashboard.visualizar'))->get('/equipamentos')->assertRedirect(route('dashboard'));
        $this->actingAs($this->usuarioCom('integracoes.gerenciar'))->get('/equipamentos')->assertOk()->assertSee('Equipamentos');
    }

    public function test_cadastro_pela_tela_grava_senha_cifrada_e_nunca_a_devolve(): void
    {
        $admin = $this->usuarioCom('integracoes.gerenciar');

        $tela = Livewire::actingAs($admin)
            ->test(EquipamentoManagement::class)
            ->call('novoEquipamento')
            ->set('form.adaptador', 'simulador')
            ->set('form.endereco_rede', '192.168.50.20')
            ->set('usuarioTecnico', 'admin')
            ->set('senhaTecnica', self::SENHA)
            ->call('salvarEquipamento')
            ->assertHasNoErrors()
            ->assertSet('senhaTecnica', '')
            ->assertSet('mode', 'detail')
            ->assertSee('Em homologação')
            ->assertSee('Cadastrada (protegida)')
            ->assertDontSee(self::SENHA);

        $equipamento = Equipamento::query()->sole();
        $credencial = $equipamento->credencialTecnicaAtiva();

        $this->assertSame(StatusEquipamento::EmHomologacao, $equipamento->status);
        $this->assertSame('bancada', $equipamento->pontoVigente()->tipo->value);
        $this->assertTrue($equipamento->modulo_seguro_rs485);
        $this->assertSame(EquipamentoCredencial::REFERENCIA_CIFRADA, $credencial->referencia_segredo);
        $this->assertSame(self::SENHA, $credencial->segredo_cifrado);
        $this->assertStringNotContainsString(self::SENHA, (string) DB::table('equipamento_credenciais')->value('segredo_cifrado'));
        $this->assertStringNotContainsString(self::SENHA, json_encode($credencial->toArray()));
        $this->assertStringNotContainsString(self::SENHA, json_encode(DB::table('auditoria_eventos')->get()).json_encode(DB::table('auditoria_alteracoes')->get()).json_encode(DB::table('auditoria_contextos')->get()));
        $this->assertStringNotContainsString(self::SENHA, $tela->html());
    }

    public function test_senha_sai_do_estado_mesmo_quando_o_cadastro_falha(): void
    {
        Livewire::actingAs($this->usuarioCom('integracoes.gerenciar'))
            ->test(EquipamentoManagement::class)
            ->call('novoEquipamento')
            ->set('form.endereco_rede', '8.8.8.8')
            ->set('senhaTecnica', self::SENHA)
            ->call('salvarEquipamento')
            ->assertSet('senhaTecnica', '')
            ->assertSee('rede local')
            ->assertDontSee(self::SENHA);

        $this->assertSame(0, Equipamento::query()->count());
    }

    public function test_testar_conexao_mostra_resultado_e_diferencia_credencial_invalida(): void
    {
        $equipamento = $this->terminalEmHomologacao();
        $admin = $this->usuarioCom('integracoes.gerenciar');

        Livewire::actingAs($admin)->test(EquipamentoManagement::class)
            ->call('selecionar', $equipamento->id)
            ->call('testarConexao')
            ->assertSet('feedback.variant', 'success')
            ->assertSee('Online');

        $this->simulador()->definirCenario($equipamento->id, CenarioSimulador::CredencialInvalida);

        Livewire::actingAs($admin)->test(EquipamentoManagement::class)
            ->call('selecionar', $equipamento->id)
            ->call('testarConexao')
            ->assertSet('feedback.variant', 'danger')
            ->assertSee('Credencial recusada');
    }

    public function test_atualizar_imagem_passa_pelo_backend_e_rota_serve_sem_cache(): void
    {
        $equipamento = $this->terminalEmHomologacao();
        $admin = $this->usuarioCom('integracoes.gerenciar');

        Livewire::actingAs($admin)->test(EquipamentoManagement::class)
            ->call('selecionar', $equipamento->id)
            ->call('atualizarImagem')
            ->assertSet('ultimaCaptura.resultado', 'confirmado')
            ->assertSee(route('equipment.image', $equipamento), false)
            ->assertDontSee('192.168.10.20/ISAPI', false);

        $resposta = $this->actingAs($admin)->get(route('equipment.image', $equipamento));
        $resposta->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('no-store', (string) $resposta->headers->get('Cache-Control'));
    }

    public function test_botao_liberar_so_aparece_com_permissao_explicita(): void
    {
        $equipamento = $this->terminalEmHomologacao();

        Livewire::actingAs($this->usuarioCom('integracoes.gerenciar'))->test(EquipamentoManagement::class)
            ->call('selecionar', $equipamento->id)
            ->assertDontSee('Liberar acesso (teste do relé)')
            ->call('abrirLiberacao')
            ->assertForbidden();
    }

    public function test_liberacao_exige_motivo_e_confirmacao_e_clique_duplo_aciona_uma_vez(): void
    {
        $equipamento = $this->terminalEmHomologacao();
        $this->simulador()->definirCenario($equipamento->id, CenarioSimulador::Aceito);
        $operador = $this->usuarioCom('integracoes.gerenciar', 'equipamentos.liberar-acesso');

        $tela = Livewire::actingAs($operador)->test(EquipamentoManagement::class)
            ->call('selecionar', $equipamento->id)
            ->call('abrirLiberacao')
            ->call('liberarAcesso')
            ->assertHasErrors(['motivoLiberacao', 'cienteLiberacao']);

        $chave = $tela->get('chaveLiberacao');
        $tela->set('motivoLiberacao', 'Teste do relé na bancada')
            ->set('cienteLiberacao', true)
            ->call('liberarAcesso')
            ->assertSee('Aceito pelo terminal')
            ->assertSee('não é comprovada');

        // Segundo envio com a mesma chave (clique duplo ou reenvio do navegador).
        $tela->set('confirmandoLiberacao', true)->set('chaveLiberacao', $chave)->call('liberarAcesso');

        $this->assertSame(1, $this->simulador()->totalAberturasFisicas($equipamento->id));
        $this->assertSame(1, $equipamento->operacoes()->where('operacao', 'abertura_remota')->count());
    }

    public function test_equipamento_de_outra_implantacao_nao_e_acessivel(): void
    {
        $equipamento = $this->terminalEmHomologacao();

        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        $intruso = $this->usuarioCom('integracoes.gerenciar');

        $this->actingAs($intruso)->get(route('equipment.image', $equipamento->id))->assertNotFound();
        Livewire::actingAs($intruso)->test(EquipamentoManagement::class)->assertDontSee($equipamento->nome);

        $this->expectException(ModelNotFoundException::class);
        Livewire::actingAs($intruso)->test(EquipamentoManagement::class)->call('selecionar', $equipamento->id);
    }
}
