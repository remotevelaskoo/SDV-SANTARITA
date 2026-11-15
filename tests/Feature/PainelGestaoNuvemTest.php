<?php

namespace Tests\Feature;

use App\Integracoes\Aplicacao\CadastroPostos;
use App\Integracoes\Aplicacao\TurnosOperacionais;
use App\Livewire\TrocarSenha;
use App\Livewire\UserManagement;
use App\Models\CaixaMovimentacao;
use App\Models\CaixaTurno;
use App\Models\Contribuicao;
use App\Models\HistoricoAcesso;
use App\Models\Implantacao;
use App\Models\Perfil;
use App\Models\Pessoa;
use App\Models\PessoaDocumento;
use App\Models\User;
use App\Services\Gestao\MetricasDia;
use App\Support\ImplantacaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\OperadorComPermissoes;
use Tests\TestCase;

/**
 * Pedido do PO (15/11/2026): o servidor é local, então o gestor não o vê de
 * fora. O nó empurra os totais do dia para a nuvem a cada 5 minutos e o
 * gestor entra no painel do Vercel com e-mail e senha.
 */
class PainelGestaoNuvemTest extends TestCase
{
    use OperadorComPermissoes, RefreshDatabase;

    private Implantacao $implantacao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->implantacao = Implantacao::factory()->create(['status' => 'ativa']);
        ImplantacaoContext::setCurrentForTesting($this->implantacao);
        config([
            'pre_cadastro_nuvem.gestao_enabled' => true,
            'pre_cadastro_nuvem.url' => 'https://portal.exemplo.test',
            'pre_cadastro_nuvem.token' => str_repeat('n', 40),
            'pre_cadastro_nuvem.implantacao_id' => $this->implantacao->id,
        ]);
    }

    protected function tearDown(): void
    {
        ImplantacaoContext::forgetCurrent();
        parent::tearDown();
    }

    private function contribuicao(string $situacao, ?float $valor, ?string $forma, ?CaixaTurno $caixa = null): void
    {
        $acesso = HistoricoAcesso::factory()->create();
        Contribuicao::query()->create([
            'historico_acesso_id' => $acesso->id, 'classificacao' => 'contribui', 'situacao_pagamento' => $situacao,
            'valor' => $valor, 'forma_pagamento' => $forma, 'caixa_turno_id' => $caixa?->id, 'registrada_em' => now(),
        ]);
    }

    public function test_metricas_do_dia_somam_contribuicoes_e_lancamentos_por_caixa(): void
    {
        $porteiro = $this->operador('validacao.registrar');
        $caixa = CaixaTurno::factory()->create(['status' => 'aberto', 'operator_id' => $porteiro->id, 'opened_at' => now()->startOfDay()->addHours(7), 'closed_at' => null, 'opening_balance' => 0]);
        $this->contribuicao('pago', 25, 'pix', $caixa);
        $this->contribuicao('pago', 25, 'dinheiro', $caixa);
        $this->contribuicao('sem_pagamento', null, null);
        $this->contribuicao('isento', null, null);
        CaixaMovimentacao::factory()->create(['caixa_turno_id' => $caixa->id, 'type' => 'entrada', 'amount' => 10, 'method' => 'dinheiro']);
        HistoricoAcesso::factory()->create(['tipo' => 'saida']);
        Pessoa::factory()->count(2)->create();

        $dados = app(MetricasDia::class)->calcular(now());

        $this->assertSame(60.0, $dados['arrecadacao']['total']);
        $this->assertSame(['dinheiro' => 35.0, 'pix' => 25.0], collect($dados['arrecadacao']['por_forma'])->sortKeys()->all());
        $this->assertSame(['pagas' => 2, 'nao_contribui' => 1, 'isentas' => 1], $dados['contribuicoes']);
        $this->assertSame(4, $dados['acessos']['entradas']);
        $this->assertSame(1, $dados['acessos']['saidas']);
        $this->assertSame(2, $dados['cadastros']['pessoas_novas']);
        $this->assertCount(1, $dados['caixas']);
        $this->assertSame($porteiro->name, $dados['caixas'][0]['operador']);
        $this->assertSame(60.0, $dados['caixas'][0]['arrecadado']);
        $this->assertSame(['dinheiro' => 35.0, 'pix' => 25.0], collect($dados['caixas'][0]['por_forma'])->sortKeys()->all());
        $this->assertSame(2, $dados['caixas'][0]['contribuicoes_pagas']);
    }

    private function gestorRemoto(string ...$chaves): User
    {
        $usuario = $this->operador(...$chaves);
        $usuario->forceFill(['acesso_painel_remoto' => true])->save();

        return $usuario;
    }

    public function test_publica_hoje_e_ontem_e_a_lista_de_quem_tem_o_painel_marcado(): void
    {
        $gestor = $this->gestorRemoto('dashboard.visualizar');
        $this->operador('dashboard.visualizar');
        Http::fake(['https://portal.exemplo.test/api/gestao' => Http::response(['ok' => true])]);

        $this->artisan('gestao:publicar-metricas')->assertSuccessful();

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $r) => $r['action'] === 'metricas' && $r['dia'] === now()->toDateString()
            && $r->hasHeader('Authorization', 'Bearer '.str_repeat('n', 40)));
        Http::assertSent(fn (Request $r) => $r['action'] === 'metricas' && $r['dia'] === now()->subDay()->toDateString());
        Http::assertSent(fn (Request $r) => $r['action'] === 'gestores' && $r['emails'] === [mb_strtolower($gestor->email)]);
    }

    public function test_lista_do_dia_leva_nome_tipo_e_pagamento_mas_nunca_documento_nem_foto(): void
    {
        $pessoa = Pessoa::factory()->create(['nome' => 'Visitante Teste']);
        PessoaDocumento::factory()->for($pessoa)->create(['valor_normalizado' => '12345678909', 'valor_apresentacao' => '123.456.789-09']);
        $acesso = HistoricoAcesso::factory()->for($pessoa)->create(['tipo' => 'entrada', 'resultado' => 'liberado']);
        Contribuicao::query()->create(['historico_acesso_id' => $acesso->id, 'classificacao' => 'contribui', 'situacao_pagamento' => 'pago', 'valor' => 25, 'forma_pagamento' => 'pix', 'registrada_em' => now()]);

        $dados = app(MetricasDia::class)->calcular(now());

        $linha = collect($dados['pessoas'])->firstWhere('nome', 'Visitante Teste');
        $this->assertNotNull($linha);
        $this->assertSame('pago', $linha['pagamento']);
        $this->assertSame(25.0, $linha['valor']);
        $this->assertSame(['hora', 'nome', 'tipo', 'resultado', 'pagamento', 'valor', 'forma', 'operador'], array_keys($linha));
        $json = (string) json_encode($dados);
        $this->assertStringNotContainsString('123.456.789-09', $json);
        $this->assertStringNotContainsString('12345678909', $json);
    }

    public function test_desligado_nao_envia_nada(): void
    {
        config(['pre_cadastro_nuvem.gestao_enabled' => false]);
        Http::fake();

        $this->artisan('gestao:publicar-metricas')->assertFailed();

        Http::assertNothingSent();
    }

    public function test_acesso_remoto_so_para_quem_tem_o_painel_marcado(): void
    {
        $gestor = $this->gestorRemoto('dashboard.visualizar');
        $semPainel = $this->operador('dashboard.visualizar');
        Http::fake(['https://portal.exemplo.test/api/gestao' => Http::response(['ready' => true])]);

        $this->artisan('gestao:acesso-remoto', ['email' => $semPainel->email])
            ->expectsQuestion('Senha do painel remoto (mínimo 10 caracteres)', 'senha-forte-123')
            ->expectsQuestion('Repita a senha', 'senha-forte-123')
            ->assertFailed();
        Http::assertNothingSent();

        $this->artisan('gestao:acesso-remoto', ['email' => $gestor->email])
            ->expectsQuestion('Senha do painel remoto (mínimo 10 caracteres)', 'senha-forte-123')
            ->expectsQuestion('Repita a senha', 'senha-forte-123')
            ->assertSuccessful();
        Http::assertSent(fn (Request $r) => $r['action'] === 'gestor' && $r['email'] === mb_strtolower($gestor->email) && $r['senha'] === 'senha-forte-123');
    }

    public function test_admin_cria_usuario_com_painel_remoto_e_senha_do_painel(): void
    {
        $admin = $this->operador('usuarios.administrar');
        $perfil = Perfil::factory()->create(['nome' => 'Gestor']);
        Http::fake(['https://portal.exemplo.test/api/gestao' => Http::response(['ready' => true])]);

        Livewire::actingAs($admin)->test(UserManagement::class)
            ->call('createUser')
            ->set('name', 'Gestora Nova')->set('username', 'gestora.nova')->set('email', 'gestora@exemplo.test')
            ->set('perfilId', $perfil->id)->set('senhaInicial', 'inicial123')->set('senhaInicial_confirmation', 'inicial123')
            ->set('acessoPainel', true)->set('senhaPainel', 'painel-remoto-1')->set('senhaPainel_confirmation', 'painel-remoto-1')
            ->call('saveUser')
            ->assertHasNoErrors()
            ->assertSee('Painel remoto pronto');

        $this->assertDatabaseHas('users', ['username' => 'gestora.nova', 'acesso_painel_remoto' => true, 'trocar_senha' => true]);
        Http::assertSent(fn (Request $r) => $r['action'] === 'gestor' && $r['email'] === 'gestora@exemplo.test' && $r['senha'] === 'painel-remoto-1');
    }

    public function test_desmarcar_o_painel_tira_o_acesso_na_nuvem_na_hora(): void
    {
        $admin = $this->operador('usuarios.administrar');
        $gestor = $this->gestorRemoto('dashboard.visualizar');
        Http::fake(['https://portal.exemplo.test/api/gestao' => Http::response(['ok' => true])]);

        Livewire::actingAs($admin)->test(UserManagement::class)
            ->call('openUser', (string) $gestor->id)
            ->call('togglePainel')
            ->assertSee('Painel remoto removido');

        $this->assertFalse($gestor->fresh()->acesso_painel_remoto);
        Http::assertSent(fn (Request $r) => $r['action'] === 'gestores' && $r['emails'] === []);
    }

    public function test_derrubar_colaborador_encerra_sessoes_libera_o_posto_e_manda_o_caixa_para_conferencia(): void
    {
        $admin = $this->operador('usuarios.administrar', TurnosOperacionais::PERMISSAO_LIBERAR);
        $porteiro = $this->operador('caixa.proprio.gerenciar');
        $posto = app(CadastroPostos::class)->criarPosto('GUARITA-KICK', 'Guarita Kick');
        app(TurnosOperacionais::class)->assumir($porteiro, $posto->id);
        $caixa = CaixaTurno::factory()->create(['status' => 'aberto', 'operator_id' => $porteiro->id, 'closed_at' => null]);
        DB::table('sessions')->insert(['id' => 'sessao-do-porteiro', 'user_id' => $porteiro->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);

        Livewire::actingAs($admin)->test(UserManagement::class)
            ->call('openUser', (string) $porteiro->id)
            ->assertSee('Online (último uso')
            ->assertSee('Guarita Kick')
            ->call('kickUser')
            ->assertHasErrors('derrubarMotivo')
            ->set('derrubarMotivo', 'Esqueceu o sistema aberto')
            ->call('kickUser')
            ->assertHasNoErrors()
            ->assertSee('Colaborador derrubado');

        $this->assertDatabaseMissing('sessions', ['id' => 'sessao-do-porteiro']);
        $this->assertSame(TurnosOperacionais::CAIXA_AGUARDANDO_CONFERENCIA, $caixa->fresh()->closing_status);
        $this->assertNull(app(TurnosOperacionais::class)->turnoAtivo($porteiro));
    }

    public function test_senha_redefinida_obriga_a_troca_no_proximo_acesso(): void
    {
        $admin = $this->operador('usuarios.administrar');
        $porteiro = $this->operador('caixa.proprio.gerenciar');

        Livewire::actingAs($admin)->test(UserManagement::class)
            ->call('openUser', (string) $porteiro->id)
            ->set('novaSenha', 'provisoria1')->set('novaSenha_confirmation', 'provisoria1')
            ->call('resetPassword')
            ->assertHasNoErrors();

        $porteiro->refresh();
        $this->assertTrue($porteiro->trocar_senha);
        $this->withoutVite();
        $this->actingAs($porteiro)->get('/caixa')->assertRedirect(route('senha.trocar'));

        Livewire::actingAs($porteiro)->test(TrocarSenha::class)
            ->set('password', 'provisoria1')->set('password_confirmation', 'provisoria1')
            ->call('salvar')
            ->assertHasErrors('password')
            ->set('password', 'minha-senha-9')->set('password_confirmation', 'minha-senha-9')
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertFalse($porteiro->fresh()->trocar_senha);
        $this->assertTrue(Hash::check('minha-senha-9', $porteiro->fresh()->password));
    }
}
