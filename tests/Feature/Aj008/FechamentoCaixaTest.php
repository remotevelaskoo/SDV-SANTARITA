<?php

namespace Tests\Feature\Aj008;

use App\Integracoes\Aplicacao\TurnosOperacionais;
use App\Livewire\AccessValidation;
use App\Livewire\CaixaFechamento;
use App\Livewire\CaixaFechamentos;
use App\Livewire\CashRegister;
use App\Models\AuditoriaEvento;
use App\Models\CaixaMovimentacao;
use App\Models\CaixaTurno;
use App\Models\Contribuicao;
use App\Models\HistoricoAcesso;
use App\Models\Implantacao;
use App\Models\OperadorPosto;
use App\Models\Pessoa;
use App\Models\PessoaDocumento;
use App\Models\Posto;
use App\Models\User;
use App\Services\CaixasOperador;
use App\Services\ContribuicoesAtendimento;
use App\Support\ImplantacaoContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Tests\Support\OperadorComPermissoes;
use Tests\TestCase;

/**
 * AJ-008 — fechamento de caixa por turno e conciliação (SDV-UXR-014 §§5.4–5.6).
 * Somente dados sintéticos.
 */
class FechamentoCaixaTest extends TestCase
{
    use OperadorComPermissoes, RefreshDatabase;

    private const PORTEIRO = ['validacao.registrar', 'caixa.proprio.gerenciar'];

    private const CPF = '529.982.247-25';

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

    // ---- Conciliação ------------------------------------------------------------

    public function test_soma_dos_pagantes_corresponde_ao_total_e_o_saldo_esperado_esta_correto(): void
    {
        $operador = $this->porteiro();
        $caixa = $this->caixas()->abrir($operador, 100);

        $pagos = [$this->atender($operador, 'contribui', '000101'), $this->atender($operador, 'contribui', '000102', 'pix'), $this->atender($operador, 'contribui', '000103', 'cartao')];
        $this->atender($operador, 'isento');
        $this->atender($operador, 'nao_contribui');
        $this->movimentar($caixa, 'entrada', 20);
        $this->movimentar($caixa, 'saida', 5);
        $this->movimentar($caixa, 'estorno', 10);

        // 100 + 45 (contribuições) + 20 (outras entradas) − 5 (saídas) − 10 (estornos)
        $this->assertSame(150.0, $caixa->fresh()->expectedBalance());

        $fechado = $this->caixas()->fechar($operador, 150, '');
        $pagantes = $this->caixas()->pagantes($fechado);

        $this->assertSame('conferido', $fechado->closing_status);
        $this->assertSame(0.0, (float) $fechado->difference);
        $this->assertSame(45.0, (float) $fechado->contribuicoes_total);
        $this->assertSame(45.0, $pagantes->sum('valor'), 'linhas pagas = total de contribuições do cabeçalho');
        $this->assertSame(45.0, round((float) Contribuicao::query()->where('situacao_pagamento', 'pago')->sum('valor'), 2), 'total de contribuições pagas na tabela canônica');
        $this->assertCount(3, $pagantes);
        $this->assertEqualsCanonicalizing(array_map(fn (Contribuicao $c) => $c->id, $pagos), $pagantes->pluck('id')->all());
        $this->assertSame(['pagantes' => 3, 'isentos' => 1, 'nao_contribuintes' => 1], $fechado->quantidades());
        $this->assertSame(20.0, (float) $fechado->outras_entradas_total);
        $this->assertSame(5.0, (float) $fechado->saidas_total);
        $this->assertSame(10.0, (float) $fechado->estornos_total);
        $this->assertSame(150.0, (float) $fechado->expected_amount);
    }

    public function test_nenhuma_contribuicao_e_contada_duas_vezes(): void
    {
        $operador = $this->porteiro();
        $caixa = $this->caixas()->abrir($operador, 0);
        $pago = $this->atender($operador, 'contribui', '000200');

        // Lançamento manual repetindo o mesmo pagamento (mesmo protocolo) não soma de novo.
        $this->movimentar($caixa, 'entrada', 15, $pago->historicoAcesso->protocol);
        $this->movimentar($caixa, 'entrada', 7);

        $caixa->refresh();
        $this->assertSame(15.0, $caixa->contribuicoesTotal());
        $this->assertSame(7.0, $caixa->outrasEntradasTotal());
        $this->assertSame(22.0, $caixa->expectedBalance());

        // O mesmo atendimento não recebe segunda contribuição.
        $this->expectException(UniqueConstraintViolationException::class);
        $this->actingAs($operador);
        app(ContribuicoesAtendimento::class)->registrar($pago->historicoAcesso, 'contribui', 'dinheiro', '000201', false, $operador);
    }

    public function test_caixas_de_operadores_e_implantacoes_diferentes_nao_se_misturam(): void
    {
        $ana = $this->porteiro();
        $bruno = $this->porteiro();
        $caixaAna = $this->caixas()->abrir($ana, 50);
        $caixaBruno = $this->caixas()->abrir($bruno, 80);

        $this->atender($ana, 'contribui', 'A-1');
        $this->atender($ana, 'contribui', 'A-2');
        $this->atender($bruno, 'contribui', 'B-1');

        // Outra implantação com caixa e pagamento do mesmo operador.
        $atual = ImplantacaoContext::current();
        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        $caixaOutra = CaixaTurno::factory()->doOperador($ana)->create(['opening_balance' => 999]);
        $historicoOutro = HistoricoAcesso::factory()->create(['operator_id' => $ana->id]);
        Contribuicao::query()->create([
            'historico_acesso_id' => $historicoOutro->id, 'classificacao' => 'contribui', 'situacao_pagamento' => 'pago',
            'valor' => '15.00', 'forma_pagamento' => 'dinheiro', 'ticket_numero' => 'X-1', 'caixa_turno_id' => $caixaOutra->id,
            'operator_id' => $ana->id, 'registrada_em' => now(), 'versao' => 1,
        ]);
        ImplantacaoContext::setCurrentForTesting($atual);

        $this->assertSame($caixaAna->id, $this->caixas()->abertoDo($ana)->id);
        $this->assertSame(2, $caixaAna->fresh()->contribuicoesPagas()->count());
        $this->assertSame(1, $caixaBruno->fresh()->contribuicoesPagas()->count());
        $this->assertSame(80.0, $caixaAna->fresh()->expectedBalance());
        $this->assertSame(95.0, $caixaBruno->fresh()->expectedBalance());
        $this->assertSame(['A-1', 'A-2'], $this->caixas()->pagantes($caixaAna)->pluck('ticket')->sort()->values()->all());
    }

    public function test_validacao_de_entrada_usa_somente_o_caixa_do_operador_autenticado(): void
    {
        $pessoa = Pessoa::factory()->create();
        $dono = $this->porteiro();
        $outro = $this->porteiro();
        $this->caixas()->abrir($dono, 0);

        // Outro operador sem caixa próprio: o pagamento é recusado e nada é gravado.
        Livewire::actingAs($outro)->test(AccessValidation::class)
            ->assertSee('Caixa fechado para você')
            ->call('selecionarPessoa', $pessoa->id)
            ->set('ticketNumero', '000300')
            ->call('release')
            ->assertHasErrors('contribution')
            ->assertSee('Abra o seu caixa antes de registrar uma contribuição paga.');

        $this->assertSame(0, HistoricoAcesso::query()->count(), 'atendimento e pagamento caem juntos');
        $this->assertSame(0, Contribuicao::query()->count());

        // Não contribuinte segue registrado mesmo sem caixa.
        Livewire::actingAs($outro)->test(AccessValidation::class)
            ->call('selecionarPessoa', $pessoa->id)
            ->set('contribution', 'no')
            ->call('release')
            ->assertHasNoErrors();
        $this->assertNull(Contribuicao::query()->sole()->caixa_turno_id);
    }

    // ---- Abertura e concorrência ----------------------------------------------

    public function test_um_caixa_aberto_por_operador_e_por_posto(): void
    {
        $operador = $this->porteiro();
        $this->caixas()->abrir($operador, 10);

        try {
            $this->caixas()->abrir($operador, 10);
            $this->fail('Segundo caixa do mesmo operador deveria ser recusado.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('já tem um caixa aberto', $e->getMessage());
        }

        // Defesa no banco: inserção concorrente direta também falha.
        $this->expectException(UniqueConstraintViolationException::class);
        CaixaTurno::factory()->doOperador($operador)->create();
    }

    public function test_troca_de_operador_exige_fechar_o_caixa_do_posto(): void
    {
        $posto = Posto::query()->create(['codigo' => 'GUARITA-TESTE', 'nome' => 'Guarita de teste', 'tipo' => Posto::TIPO_PRINCIPAL, 'status' => 'ativo']);
        $primeiro = $this->porteiro();
        $segundo = $this->porteiro();
        foreach ([$primeiro, $segundo] as $usuario) {
            OperadorPosto::query()->create(['user_id' => $usuario->id, 'posto_id' => $posto->id, 'started_at' => now()]);
            app(TurnosOperacionais::class)->assumir($usuario, $posto->id);
        }

        $caixa = $this->caixas()->abrir($primeiro, 0);
        $this->assertSame($posto->id, $caixa->posto_id);
        $this->assertSame('Guarita de teste', $caixa->terminal);

        try {
            $this->caixas()->abrir($segundo, 0);
            $this->fail('O posto já tem caixa aberto.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('já tem um caixa aberto por outro operador', $e->getMessage());
        }

        $this->caixas()->fechar($primeiro, 0, '');
        $this->assertSame($posto->id, $this->caixas()->abrir($segundo, 0)->posto_id);
    }

    // ---- Fechamento ---------------------------------------------------------------

    public function test_diferenca_sem_justificativa_e_bloqueada(): void
    {
        $operador = $this->porteiro();
        $caixa = $this->caixas()->abrir($operador, 100);
        $this->atender($operador, 'contribui', '000400');

        foreach (['', 'curta'] as $justificativa) {
            try {
                $this->caixas()->fechar($operador, 110, $justificativa);
                $this->fail('Diferença sem justificativa deveria ser bloqueada.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('closingNotes', $e->errors());
            }
        }
        $this->assertSame('aberto', $caixa->fresh()->status);

        $fechado = $this->caixas()->fechar($operador, 110, 'Faltou troco de cinco reais no fim do turno.');
        $this->assertSame('diferenca', $fechado->closing_status);
        $this->assertSame(-5.0, (float) $fechado->difference);
        $this->assertDatabaseHas('auditoria_eventos', ['action' => 'caixa_diferenca_registrada', 'entity_id' => $caixa->id, 'justification' => 'Faltou troco de cinco reais no fim do turno.']);
    }

    public function test_fechamento_e_imutavel_e_preserva_o_historico(): void
    {
        $operador = $this->porteiro();
        $caixa = $this->caixas()->abrir($operador, 0);
        $pago = $this->atender($operador, 'contribui', '000500');
        $fechado = $this->caixas()->fechar($operador, 15, '');

        $this->assertThrows(fn () => $fechado->update(['informed_amount' => 999]), LogicException::class);
        $this->assertThrows(fn () => $fechado->delete(), LogicException::class);
        $this->assertThrows(fn () => $this->movimentar($caixa, 'entrada', 1), LogicException::class);
        $this->assertThrows(fn () => $pago->update(['valor' => '1.00']), LogicException::class);
        $this->assertThrows(fn () => $pago->delete(), LogicException::class);

        $this->assertSame(15.0, (float) $caixa->fresh()->contribuicoes_total);
        $this->assertSame(15.0, (float) $caixa->fresh()->informed_amount);
    }

    public function test_correcao_posterior_de_ticket_fica_sinalizada_sem_alterar_o_fechamento(): void
    {
        $operador = $this->porteiro();
        $this->caixas()->abrir($operador, 0);
        $pago = $this->atender($operador, 'contribui', '000600');
        $fechado = $this->caixas()->fechar($operador, 15, '');

        $gestor = $this->operador('caixa.consolidado.consultar', ContribuicoesAtendimento::PERMISSAO_CORRECAO);
        $this->actingAs($gestor);
        app(ContribuicoesAtendimento::class)->corrigirTicket($pago->id, '000601', 'Ticket digitado errado no atendimento.', $gestor);

        $linha = $this->caixas()->pagantes($fechado->fresh())->sole();
        $this->assertSame('000601', $linha['ticket']);
        $this->assertSame('000600', $linha['ticket_anterior']);
        $this->assertNotNull($linha['ticket_corrigido_em']);
        $this->assertSame(15.0, (float) $fechado->fresh()->contribuicoes_total);

        Livewire::actingAs($gestor)->test(CaixaFechamento::class, ['turno' => $fechado->id])
            ->assertSee('000601')
            ->assertSee('anterior: 000600');
    }

    // ---- Tela, impressão e exportação -------------------------------------------

    public function test_fluxo_completo_da_validacao_ao_resumo_e_impressao(): void
    {
        $this->withoutVite();
        $operador = $this->porteiro();
        $operador->update(['name' => 'Operadora Sintética']);
        $pessoa = Pessoa::factory()->create(['nome' => 'Visitante Sintético']);
        PessoaDocumento::factory()->for($pessoa)->create(['tipo' => 'cpf', 'valor_apresentacao' => self::CPF, 'valor_normalizado' => '52998224725']);

        Livewire::actingAs($operador)->test(CashRegister::class)
            ->set('openingBalanceInput', '50,00')
            ->call('openRegister')
            ->assertSet('status', 'aberto');

        $validacao = Livewire::actingAs($operador)->test(AccessValidation::class)
            ->assertSee('Caixa aberto')
            ->call('selecionarPessoa', $pessoa->id)
            ->set('ticketNumero', '000123')
            ->set('paymentMethod', 'pix')
            ->call('release')
            ->assertHasNoErrors();
        $protocolo = $validacao->get('protocol');

        $caixa = CaixaTurno::query()->sole();
        Livewire::actingAs($operador)->test(CashRegister::class)
            ->assertSee('Contribuições pagas (1)')
            ->set('informedAmount', '65,00')
            ->call('closeRegister')
            ->assertHasNoErrors()
            ->assertRedirect(route('cash-register.closing', $caixa->id));

        $this->actingAs($operador)->get(route('cash-register.closing', $caixa->id))
            ->assertOk()
            ->assertSee('Imprimir fechamento')
            ->assertSee('Exportar itens em CSV')
            ->assertSee('Conferido')
            ->assertSee('Visitante Sintético')
            ->assertSee('***.***.247-**')
            ->assertDontSee(self::CPF)
            ->assertSee($protocolo)
            ->assertSee('000123')
            ->assertSee('R$ 15,00')
            ->assertSee('Pix')
            ->assertSee('Operadora Sintética')
            ->assertSee('Portaria Principal')
            ->assertSee('R$ 65,00');

        $impressao = $this->actingAs($operador)->get(route('cash-register.closing.print', $caixa->id))
            ->assertOk()
            ->assertSee('Imprimir fechamento')
            ->assertSee('Visitante Sintético')
            ->assertSee('Emitido em')
            ->assertDontSee('Módulos do sistema');
        $this->assertStringNotContainsString('class="navigation"', $impressao->getContent());

        $this->assertDatabaseHas('auditoria_eventos', ['action' => 'caixa_aberto', 'entity_id' => $caixa->id, 'actor_id' => $operador->id]);
        $this->assertDatabaseHas('auditoria_eventos', ['action' => 'caixa_fechado', 'entity_id' => $caixa->id, 'actor_id' => $operador->id]);
        $this->assertDatabaseHas('auditoria_eventos', ['action' => 'imprimiu_fechamento_caixa', 'entity_id' => $caixa->id]);
    }

    public function test_exportacao_csv_e_auditada_e_mascara_o_documento(): void
    {
        $operador = $this->porteiro();
        $this->caixas()->abrir($operador, 0);
        $pessoa = Pessoa::factory()->create();
        PessoaDocumento::factory()->for($pessoa)->create(['tipo' => 'cpf', 'valor_apresentacao' => self::CPF, 'valor_normalizado' => '52998224725']);
        $this->atender($operador, 'contribui', '000700', 'dinheiro', $pessoa);
        $fechado = $this->caixas()->fechar($operador, 15, '');

        $resposta = Livewire::actingAs($operador)->test(CaixaFechamento::class, ['turno' => $fechado->id])
            ->call('exportarCsv')
            ->assertFileDownloaded('fechamento-'.$fechado->numero_fechamento.'.csv');

        $conteudo = base64_decode($resposta->effects['download']['content']);
        $this->assertStringContainsString("'000700", $conteudo);
        $this->assertStringContainsString('***.***.247-**', $conteudo);
        $this->assertStringNotContainsString(self::CPF, $conteudo);
        $this->assertDatabaseHas('auditoria_eventos', ['action' => 'exportou_fechamento_caixa', 'entity_id' => $fechado->id, 'actor_id' => $operador->id]);
    }

    // ---- Permissões -----------------------------------------------------------------

    public function test_porteiro_consulta_so_os_proprios_e_gestao_consulta_todos(): void
    {
        $this->withoutVite();
        $ana = $this->porteiro();
        $bruno = $this->porteiro();
        $this->caixas()->abrir($ana, 0);
        $this->caixas()->abrir($bruno, 0);
        $daAna = $this->caixas()->fechar($ana, 0, '');
        $doBruno = $this->caixas()->fechar($bruno, 0, '');

        // Porteiro/Caixa: só os próprios.
        $this->actingAs($ana)->get(route('cash-register.closing', $doBruno->id))->assertForbidden();
        $this->actingAs($ana)->get(route('cash-register.closing.print', $doBruno->id))->assertForbidden();
        Livewire::actingAs($ana)->test(CaixaFechamentos::class)
            ->assertSee($daAna->numero_fechamento)
            ->assertDontSee($doBruno->numero_fechamento);
        Livewire::actingAs($ana)->test(CashRegister::class)
            ->assertSee($daAna->numero_fechamento)
            ->assertDontSee($doBruno->numero_fechamento);
        $this->assertSame(0, AuditoriaEvento::query()->where('action', 'consultou_fechamento_caixa')->count(), 'consulta do próprio não é consolidada');

        // Gestor, Auditor e Administrador: todos da implantação, com auditoria.
        foreach (['Gestor' => [...self::PORTEIRO, 'caixa.consolidado.consultar'], 'Auditor' => ['caixa.consolidado.consultar']] as $papel => $permissoes) {
            $consulta = $this->operador(...$permissoes);
            Livewire::actingAs($consulta)->test(CaixaFechamentos::class)
                ->assertSee($daAna->numero_fechamento)
                ->assertSee($doBruno->numero_fechamento);
            $this->actingAs($consulta)->get(route('cash-register.closing', $doBruno->id))->assertOk();
            $this->assertDatabaseHas('auditoria_eventos', ['action' => 'consultou_fechamento_caixa', 'entity_id' => $doBruno->id, 'actor_id' => $consulta->id]);
            $this->assertDatabaseHas('auditoria_eventos', ['action' => 'consultou_fechamentos_consolidados', 'actor_id' => $consulta->id]);
        }
    }

    public function test_sem_permissao_de_caixa_nenhuma_rota_de_fechamento_abre(): void
    {
        $ana = $this->porteiro();
        $this->caixas()->abrir($ana, 0);
        $fechado = $this->caixas()->fechar($ana, 0, '');
        $semCaixa = $this->operador('validacao.registrar');

        foreach (['cash-register.closings' => [], 'cash-register.closing' => [$fechado->id], 'cash-register.closing.print' => [$fechado->id]] as $rota => $parametros) {
            $this->actingAs($semCaixa)->get(route($rota, $parametros))->assertRedirect(route('dashboard'));
        }
        auth()->logout();
        $this->get(route('cash-register.closing', $fechado->id))->assertRedirect(route('login'));
    }

    public function test_fechamento_anterior_ao_aj008_avisa_que_nao_tem_detalhamento(): void
    {
        $operador = $this->porteiro();
        $antigo = CaixaTurno::factory()->doOperador($operador)->create();
        DB::table('caixa_turnos')->where('id', $antigo->id)->update([
            'status' => 'fechado', 'closed_at' => now(), 'expected_amount' => 200, 'informed_amount' => 200,
            'difference' => 0, 'closing_status' => 'conferido',
        ]);

        Livewire::actingAs($operador)->test(CaixaFechamento::class, ['turno' => $antigo->id])
            ->assertSee('Fechamento anterior ao detalhamento por categoria')
            ->assertDontSee('+ Contribuições pagas');
    }

    public function test_fechamento_de_outra_implantacao_nao_abre(): void
    {
        $atual = ImplantacaoContext::current();
        $gestor = $this->operador('caixa.consolidado.consultar');
        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        $alheio = CaixaTurno::factory()->create(['status' => 'aberto']);
        DB::table('caixa_turnos')->where('id', $alheio->id)->update(['status' => 'fechado', 'closed_at' => now()]);
        ImplantacaoContext::setCurrentForTesting($atual);

        $this->expectException(ModelNotFoundException::class);
        Livewire::actingAs($gestor)->test(CaixaFechamento::class, ['turno' => $alheio->id]);
    }

    // ---- Auxiliares -------------------------------------------------------------------

    private function caixas(): CaixasOperador
    {
        return app(CaixasOperador::class);
    }

    private function porteiro(): User
    {
        return $this->operador(...self::PORTEIRO);
    }

    /** Atendimento com contribuição pela mesma regra da Validação de Entrada, em uma transação. */
    private function atender(User $operador, string $classificacao, ?string $ticket = null, string $forma = 'dinheiro', ?Pessoa $pessoa = null): Contribuicao
    {
        $this->actingAs($operador);

        return DB::transaction(function () use ($operador, $classificacao, $ticket, $forma, $pessoa): Contribuicao {
            $historico = HistoricoAcesso::factory()->create([
                'operator_id' => $operador->id,
                'pessoa_id' => ($pessoa ?? Pessoa::factory()->create())->id,
                'protocol' => 'SRA-TESTE-'.Str::upper(Str::random(8)),
            ]);

            return app(ContribuicoesAtendimento::class)->registrar($historico, $classificacao, $forma, $ticket, true, $operador)->load('historicoAcesso');
        });
    }

    private function movimentar(CaixaTurno $caixa, string $tipo, float $valor, ?string $protocolo = null): CaixaMovimentacao
    {
        return CaixaMovimentacao::query()->create([
            'caixa_turno_id' => $caixa->id,
            'type' => $tipo,
            'amount' => $valor,
            'method' => 'dinheiro',
            'description' => "Lançamento sintético de {$tipo}",
            'protocol' => $protocolo,
            'operator_id' => $caixa->operator_id,
            'occurred_at' => now(),
        ]);
    }
}
