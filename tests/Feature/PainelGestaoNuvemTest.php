<?php

namespace Tests\Feature;

use App\Livewire\AcessoPainelRemoto;
use App\Models\CaixaMovimentacao;
use App\Models\CaixaTurno;
use App\Models\Contribuicao;
use App\Models\HistoricoAcesso;
use App\Models\Implantacao;
use App\Models\Pessoa;
use App\Services\Gestao\MetricasDia;
use App\Support\ImplantacaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
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

    public function test_publica_hoje_e_ontem_e_a_lista_de_quem_tem_dashboard_sem_dados_de_visitante(): void
    {
        $gestor = $this->operador('dashboard.visualizar');
        $this->operador('validacao.registrar');
        Pessoa::factory()->create(['nome' => 'Visitante Sigiloso']);
        Http::fake(['https://portal.exemplo.test/api/gestao' => Http::response(['ok' => true])]);

        $this->artisan('gestao:publicar-metricas')->assertSuccessful();

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $r) => $r['action'] === 'metricas' && $r['dia'] === now()->toDateString()
            && $r->hasHeader('Authorization', 'Bearer '.str_repeat('n', 40)));
        Http::assertSent(fn (Request $r) => $r['action'] === 'metricas' && $r['dia'] === now()->subDay()->toDateString());
        Http::assertSent(fn (Request $r) => $r['action'] === 'gestores' && $r['emails'] === [mb_strtolower($gestor->email)]);
        Http::assertNotSent(fn (Request $r) => str_contains($r->body(), 'Visitante Sigiloso'));
    }

    public function test_desligado_nao_envia_nada(): void
    {
        config(['pre_cadastro_nuvem.gestao_enabled' => false]);
        Http::fake();

        $this->artisan('gestao:publicar-metricas')->assertFailed();

        Http::assertNothingSent();
    }

    public function test_acesso_remoto_so_para_quem_tem_dashboard(): void
    {
        $gestor = $this->operador('dashboard.visualizar');
        $porteiro = $this->operador('validacao.registrar');
        Http::fake(['https://portal.exemplo.test/api/gestao' => Http::response(['ready' => true])]);

        $this->artisan('gestao:acesso-remoto', ['email' => $porteiro->email])
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

    public function test_gestor_cria_a_propria_senha_remota_pela_tela_confirmando_a_senha_do_sdv(): void
    {
        $gestor = $this->operador('dashboard.visualizar');
        $gestor->forceFill(['password' => Hash::make('senha-do-sdv-1')])->save();
        Http::fake(['https://portal.exemplo.test/api/gestao' => Http::response(['ready' => true])]);

        Livewire::actingAs($gestor)->test(AcessoPainelRemoto::class)
            ->set('senha', 'painel-remoto-1')->set('senha_confirmation', 'painel-remoto-1')->set('senhaAtual', 'errada')
            ->call('salvar')
            ->assertHasErrors('senhaAtual');
        Http::assertNothingSent();

        Livewire::actingAs($gestor)->test(AcessoPainelRemoto::class)
            ->set('senha', 'painel-remoto-1')->set('senha_confirmation', 'painel-remoto-1')->set('senhaAtual', 'senha-do-sdv-1')
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertSet('senha', '')
            ->assertSee('Acesso remoto pronto');
        Http::assertSent(fn (Request $r) => $r['action'] === 'gestor' && $r['email'] === mb_strtolower($gestor->email) && $r['senha'] === 'painel-remoto-1');
    }

    public function test_tela_de_acesso_remoto_exige_dashboard(): void
    {
        $porteiro = $this->operador('validacao.registrar');

        Livewire::actingAs($porteiro)->test(AcessoPainelRemoto::class)->assertForbidden();
    }
}
