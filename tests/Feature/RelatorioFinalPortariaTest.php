<?php

namespace Tests\Feature;

use App\Livewire\Portaria;
use App\Livewire\RelatorioFinalPortaria;
use App\Models\CaixaMovimentacao;
use App\Models\CaixaTurno;
use App\Models\Contribuicao;
use App\Models\HistoricoAcesso;
use App\Models\Implantacao;
use App\Models\Pessoa;
use App\Models\User;
use App\Support\ImplantacaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\OperadorComPermissoes;
use Tests\TestCase;

class RelatorioFinalPortariaTest extends TestCase
{
    use OperadorComPermissoes, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
    }

    protected function tearDown(): void
    {
        ImplantacaoContext::forgetCurrent();
        parent::tearDown();
    }

    public function test_modo_portaria_mostra_o_atalho_do_relatorio_final(): void
    {
        $this->withoutVite();
        $this->actingAs($this->operador('validacao.registrar'));

        $this->get('/portaria')
            ->assertOk()
            ->assertSee('Relatório final')
            ->assertSee(route('portaria.relatorio-final'), false);
    }

    public function test_relatorio_exige_permissao_da_portaria(): void
    {
        $this->withoutVite();
        $this->actingAs($this->operador('relatorios.proprio.consultar'));

        // O middleware de permissão devolve para a área inicial com aviso.
        $this->get('/portaria/relatorio-final')->assertRedirect();
    }

    public function test_relatorio_lista_entradas_saidas_valores_total_e_tickets_por_pessoa(): void
    {
        $operador = $this->operador('validacao.registrar', 'caixa.proprio.gerenciar');
        $ana = Pessoa::factory()->create(['nome' => 'Ana Contribuinte']);
        $bruno = Pessoa::factory()->create(['nome' => 'Bruno Isento']);

        $this->contribuicao($operador, $ana, '000123', '15.00', 'dinheiro');
        $this->contribuicao($operador, $ana, '000124', '15.00', 'pix');
        $saida = HistoricoAcesso::factory()->create(['pessoa_id' => $bruno->id, 'tipo' => 'saida', 'resultado' => 'liberado', 'operator_id' => $operador->id, 'occurred_at' => now()]);
        Contribuicao::query()->create([
            'historico_acesso_id' => $saida->id, 'pessoa_id' => $bruno->id, 'classificacao' => 'isento',
            'situacao_pagamento' => 'isento', 'operator_id' => $operador->id, 'registrada_em' => now(), 'versao' => 1,
        ]);

        $turno = CaixaTurno::query()->create(['terminal' => 'Caixa 01', 'operator_id' => $operador->id, 'opened_at' => now(), 'opening_balance' => 100, 'status' => 'aberto']);
        CaixaMovimentacao::query()->create(['caixa_turno_id' => $turno->id, 'type' => 'entrada', 'amount' => 20, 'method' => 'dinheiro', 'description' => 'Taxa de evento', 'operator_id' => $operador->id, 'occurred_at' => now()]);
        CaixaMovimentacao::query()->create(['caixa_turno_id' => $turno->id, 'type' => 'estorno', 'amount' => 5, 'method' => 'dinheiro', 'description' => 'Troco devolvido', 'operator_id' => $operador->id, 'occurred_at' => now()]);

        // Fora do período: não entra no relatório de hoje.
        $this->contribuicao($operador, $ana, '999999', '15.00', 'dinheiro', now()->subDays(3));

        Livewire::actingAs($operador)->test(RelatorioFinalPortaria::class)
            ->assertSee('Ana Contribuinte')
            ->assertSee('Bruno Isento')
            ->assertSee('000123, 000124')
            ->assertDontSee('999999')
            ->assertSee('Taxa de evento')
            ->assertViewHas('resumo', fn (array $r) => $r['entradas'] === 2 && $r['saidas'] === 1 && $r['pagos'] === 2 && $r['isentos'] === 1)
            ->assertViewHas('financeiro', fn (array $f) => $f['contribuicoes'] === 30.0 && $f['tickets'] === 2 && $f['outrasEntradas'] === 20.0 && $f['estornos'] === 5.0 && $f['totalArrecadado'] === 45.0)
            ->assertViewHas('contribuintes', fn ($c) => $c->count() === 1 && $c->first()['tickets'] === ['000123', '000124'] && $c->first()['total'] === 30.0)
            ->assertSee('R$ 45,00')
            ->set('dateFrom', now()->subDays(3)->toDateString())
            ->assertSee('999999')
            ->assertViewHas('financeiro', fn (array $f) => $f['contribuicoes'] === 45.0);
    }

    public function test_sem_permissao_de_caixa_os_valores_ficam_ocultos(): void
    {
        $operador = $this->operador('validacao.registrar');
        $this->contribuicao($operador, Pessoa::factory()->create(['nome' => 'Carla Visitante']), '000555', '15.00', 'pix');

        Livewire::actingAs($operador)->test(RelatorioFinalPortaria::class)
            ->assertSee('Carla Visitante')
            ->assertSee('Valores ocultos')
            ->assertDontSee('Total de arrecadação')
            ->assertDontSee('R$ 15,00');
    }

    public function test_sem_visao_consolidada_os_valores_sao_so_do_proprio_operador(): void
    {
        $eu = $this->operador('validacao.registrar', 'caixa.proprio.gerenciar');
        $colega = $this->operador('validacao.registrar', 'caixa.proprio.gerenciar');
        $this->contribuicao($eu, Pessoa::factory()->create(['nome' => 'Pessoa Minha']), '000001', '15.00', 'pix');
        $this->contribuicao($colega, Pessoa::factory()->create(['nome' => 'Pessoa Colega']), '000002', '15.00', 'pix');

        Livewire::actingAs($eu)->test(RelatorioFinalPortaria::class)
            ->assertSee('Pessoa Colega') // as entradas e saídas cobrem toda a portaria
            ->assertViewHas('financeiro', fn (array $f) => $f['contribuicoes'] === 15.0)
            ->assertViewHas('contribuintes', fn ($c) => $c->pluck('pessoa')->all() === ['Pessoa Minha']);

        Livewire::actingAs($this->operador('validacao.registrar', 'caixa.consolidado.consultar'))->test(RelatorioFinalPortaria::class)
            ->assertViewHas('financeiro', fn (array $f) => $f['contribuicoes'] === 30.0);
    }

    public function test_atalho_do_relatorio_aparece_nos_atalhos_do_componente(): void
    {
        $this->actingAs($this->operador('validacao.registrar'));

        Livewire::test(Portaria::class)->assertSee('Entradas, saídas, arrecadação e tickets do período');
    }

    private function contribuicao(User $operador, Pessoa $pessoa, string $ticket, string $valor, string $forma, $quando = null): Contribuicao
    {
        $quando ??= now();
        $historico = HistoricoAcesso::factory()->create([
            'pessoa_id' => $pessoa->id, 'tipo' => 'entrada', 'resultado' => 'liberado',
            'operator_id' => $operador->id, 'occurred_at' => $quando,
        ]);

        return Contribuicao::query()->create([
            'historico_acesso_id' => $historico->id,
            'pessoa_id' => $pessoa->id,
            'classificacao' => 'contribui',
            'situacao_pagamento' => 'pago',
            'valor' => $valor,
            'forma_pagamento' => $forma,
            'ticket_numero' => $ticket,
            'operator_id' => $operador->id,
            'registrada_em' => $quando,
            'versao' => 1,
        ]);
    }
}
