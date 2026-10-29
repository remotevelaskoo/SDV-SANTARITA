<?php

namespace Tests\Feature\Aj006;

use App\Livewire\AccessValidation;
use App\Livewire\Reports;
use App\Models\AuditoriaEvento;
use App\Models\CaixaTurno;
use App\Models\Contribuicao;
use App\Models\ContribuicaoTicketCorrecao;
use App\Models\HistoricoAcesso;
use App\Models\Imovel;
use App\Models\Implantacao;
use App\Models\Pessoa;
use App\Models\Vinculo;
use App\Support\ImplantacaoContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\OperadorComPermissoes;
use Tests\TestCase;

/** AJ-006, fatia 2 — número do ticket de acesso e relatório de acessos. */
class TicketContribuicaoTest extends TestCase
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

    public function test_contribuicao_paga_exige_ticket_e_preserva_zeros_a_esquerda(): void
    {
        $pessoa = $this->pessoa();
        $operador = $this->operador('validacao.registrar');
        $caixa = CaixaTurno::factory()->doOperador($operador)->create();

        $teste = Livewire::actingAs($operador)
            ->test(AccessValidation::class)
            ->call('selecionarPessoa', $pessoa->id)
            ->set('contribution', 'yes')
            ->call('release')
            ->assertHasErrors(['ticketNumero' => 'required_if']);

        $this->assertSame(0, HistoricoAcesso::query()->count());

        $teste->set('ticketNumero', '000123')->call('release')->assertHasNoErrors();

        $contribuicao = Contribuicao::query()->sole();
        $this->assertSame('000123', $contribuicao->ticket_numero);
        $this->assertSame('pago', $contribuicao->situacao_pagamento);
        $this->assertSame('15.00', $contribuicao->valor);
        $this->assertSame('dinheiro', $contribuicao->forma_pagamento);
        $this->assertSame($teste->get('protocol'), $contribuicao->historicoAcesso->protocol);
        $this->assertSame($caixa->id, $contribuicao->caixa_turno_id, 'pagamento entra no caixa do próprio operador');
    }

    public function test_ticket_e_opcional_para_nao_contribuinte_e_isento(): void
    {
        $pessoa = $this->pessoa();

        Livewire::actingAs($this->operador('validacao.registrar'))
            ->test(AccessValidation::class)
            ->call('selecionarPessoa', $pessoa->id)
            ->set('contribution', 'no')
            ->call('savePending')
            ->assertHasNoErrors()
            ->call('startNewValidation')
            ->call('selecionarPessoa', $pessoa->id)
            ->set('contribution', 'exempt')
            ->set('ticketNumero', 'ISENTO-7')
            ->call('release')
            ->assertHasNoErrors();

        $this->assertEqualsCanonicalizing(['sem_pagamento', 'isento'], Contribuicao::query()->pluck('situacao_pagamento')->all());
        $this->assertSame(1, Contribuicao::query()->whereNull('valor')->whereNull('ticket_numero')->count());
    }

    public function test_ticket_repetido_alerta_e_so_segue_com_confirmacao(): void
    {
        $pessoa = $this->pessoa();
        $operador = $this->operador('validacao.registrar');
        CaixaTurno::factory()->doOperador($operador)->create();

        Livewire::actingAs($operador)->test(AccessValidation::class)
            ->call('selecionarPessoa', $pessoa->id)->set('ticketNumero', '0042')->call('release')->assertHasNoErrors();

        Livewire::actingAs($operador)->test(AccessValidation::class)
            ->call('selecionarPessoa', $pessoa->id)
            ->set('ticketNumero', '0042')
            ->assertSee('Ticket já registrado')
            ->call('release')
            ->assertHasErrors('ticketNumero')
            ->set('ticketDuplicadoConfirmado', true)
            ->call('release')
            ->assertHasNoErrors();

        $this->assertSame(2, Contribuicao::query()->where('ticket_numero', '0042')->count());
        $this->assertTrue(Contribuicao::query()->latest('registrada_em')->latest('id')->where('ticket_duplicado', true)->exists());
    }

    public function test_correcao_exige_permissao_justificativa_e_preserva_valor_anterior(): void
    {
        $contribuicao = $this->contribuicaoRegistrada('00077');
        $semPermissao = $this->operador('relatorios.consolidado.consultar');

        Livewire::actingAs($semPermissao)->test(Reports::class)
            ->assertDontSee('Corrigir</button>', escape: false)
            ->call('startTicketCorrection', $contribuicao->id)
            ->assertForbidden();

        $gestor = $this->operador('relatorios.consolidado.consultar', 'contribuicao.corrigir-ticket');

        Livewire::actingAs($gestor)->test(Reports::class)
            ->call('startTicketCorrection', $contribuicao->id)
            ->set('novoTicket', '00078')
            ->set('justificativaTicket', 'curta')
            ->call('saveTicketCorrection')
            ->assertHasErrors('justificativaTicket')
            ->set('justificativaTicket', 'Digitação trocou o último dígito do ticket físico.')
            ->call('saveTicketCorrection')
            ->assertHasNoErrors()
            ->assertSet('correctingContribuicaoId', null);

        $this->assertSame('00078', $contribuicao->fresh()->ticket_numero);
        $this->assertSame(2, $contribuicao->fresh()->versao);
        $correcao = ContribuicaoTicketCorrecao::query()->sole();
        $this->assertSame('00077', $correcao->valor_anterior);
        $this->assertSame('00078', $correcao->valor_novo);

        $evento = AuditoriaEvento::query()->where('action', 'corrigiu_ticket_contribuicao')->sole();
        $this->assertSame('Digitação trocou o último dígito do ticket físico.', $evento->justification);

        $this->expectException(\LogicException::class);
        $correcao->update(['valor_novo' => 'X']);
    }

    public function test_correcao_nao_pode_apagar_ticket_de_contribuicao_paga(): void
    {
        $contribuicao = $this->contribuicaoRegistrada('5');

        Livewire::actingAs($this->operador('relatorios.consolidado.consultar', 'contribuicao.corrigir-ticket'))
            ->test(Reports::class)
            ->call('startTicketCorrection', $contribuicao->id)
            ->set('novoTicket', '')
            ->set('justificativaTicket', 'Tentativa de remover o ticket pago.')
            ->call('saveTicketCorrection')
            ->assertHasErrors('novoTicket');

        $this->assertSame('5', $contribuicao->fresh()->ticket_numero);
    }

    public function test_relatorio_mostra_e_filtra_pagamento_e_ticket_com_valor_conforme_permissao(): void
    {
        $paga = $this->contribuicaoRegistrada('000900');
        $historicoIsento = HistoricoAcesso::factory()->create(['protocol' => 'SRA-ISENTO-1']);
        Contribuicao::query()->create([
            'historico_acesso_id' => $historicoIsento->id, 'classificacao' => 'isento', 'situacao_pagamento' => 'isento',
            'registrada_em' => now(), 'versao' => 1,
        ]);
        HistoricoAcesso::factory()->create(['protocol' => 'SRA-SEM-REGISTRO']);

        $auditor = $this->operador('relatorios.consolidado.consultar');
        Livewire::actingAs($auditor)->test(Reports::class)
            ->assertSee('000900')
            ->assertSee('Contribui/Pago')
            ->assertDontSee('<th>Valor e forma</th>', escape: false)
            ->set('paymentStatusFilter', 'isento')
            ->assertSee('SRA-ISENTO-1')
            ->assertDontSee($paga->historicoAcesso->protocol)
            ->set('paymentStatusFilter', 'sem_registro')
            ->assertSee('SRA-SEM-REGISTRO')
            ->assertDontSee('SRA-ISENTO-1')
            ->set('paymentStatusFilter', 'todos')
            ->set('ticketFilter', '000900')
            ->assertSee($paga->historicoAcesso->protocol)
            ->assertDontSee('SRA-ISENTO-1');

        Livewire::actingAs($this->operador('relatorios.consolidado.consultar', 'caixa.consolidado.consultar'))
            ->test(Reports::class)
            ->assertSee('<th>Valor e forma</th>', escape: false)
            ->assertSee('R$ 15,00');
    }

    public function test_exportacao_aplica_os_mesmos_controles_e_audita_sem_dados_sensiveis(): void
    {
        $this->contribuicaoRegistrada('000900');

        $semFinanceiro = Livewire::actingAs($this->operador('relatorios.consolidado.consultar'))
            ->test(Reports::class)->call('exportCsv');
        $csv = $semFinanceiro->effects['download']['content'] ?? '';
        $csv = base64_decode($csv, true) ?: $csv;
        $this->assertStringContainsString('Situação do pagamento', $csv);
        $this->assertStringContainsString("'000900", $csv);
        $this->assertStringNotContainsString('Forma de pagamento', $csv);
        $this->assertStringNotContainsString('15,00', $csv);

        $comFinanceiro = Livewire::actingAs($this->operador('relatorios.consolidado.consultar', 'caixa.consolidado.consultar'))
            ->test(Reports::class)->call('exportCsv');
        $csv = $comFinanceiro->effects['download']['content'] ?? '';
        $csv = base64_decode($csv, true) ?: $csv;
        $this->assertStringContainsString('Forma de pagamento', $csv);
        $this->assertStringContainsString('15,00', $csv);

        $metadados = AuditoriaEvento::query()->where('action', 'exportou_csv')->get()->map(fn ($e) => $e->context->metadata);
        $this->assertEqualsCanonicalizing([false, true], $metadados->pluck('colunas_financeiras')->all());
    }

    public function test_contribuicao_de_outra_implantacao_nao_aparece_nem_pode_ser_corrigida(): void
    {
        $atual = ImplantacaoContext::current();
        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        $outra = $this->contribuicaoRegistrada('OUTRA-1');
        ImplantacaoContext::setCurrentForTesting($atual);

        $teste = Livewire::actingAs($this->operador('relatorios.consolidado.consultar', 'contribuicao.corrigir-ticket'))
            ->test(Reports::class)
            ->assertDontSee('OUTRA-1');

        $this->expectException(ModelNotFoundException::class);
        $teste->call('startTicketCorrection', $outra->id);
    }

    private function pessoa(): Pessoa
    {
        $pessoa = Pessoa::factory()->create(['nome' => 'Pessoa Atendida']);
        Vinculo::factory()->for($pessoa, 'pessoa')->for(Imovel::factory()->create(), 'imovel')->create(['ended_at' => null]);

        return $pessoa;
    }

    private function contribuicaoRegistrada(string $ticket): Contribuicao
    {
        $historico = HistoricoAcesso::factory()->create();

        return Contribuicao::query()->create([
            'historico_acesso_id' => $historico->id,
            'classificacao' => 'contribui',
            'situacao_pagamento' => 'pago',
            'valor' => '15.00',
            'forma_pagamento' => 'pix',
            'ticket_numero' => $ticket,
            'registrada_em' => now(),
            'versao' => 1,
        ]);
    }
}
