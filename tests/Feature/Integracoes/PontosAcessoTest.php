<?php

namespace Tests\Feature\Integracoes;

use App\Integracoes\Aplicacao\ComandosAbertura;
use App\Integracoes\Aplicacao\TesteReleBancada;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Integracoes\Dominio\Enums\StatusPontoAcesso;
use App\Livewire\PontoAcessoManagement;
use App\Models\AuditoriaEvento;
use App\Models\PontoAcesso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\TestCase;

/**
 * Tela de pontos de acesso: da bancada ao ponto real, com o relé homologado
 * de novo no ponto antes de ativar.
 */
class PontosAcessoTest extends TestCase
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

    public function test_rota_exige_permissao(): void
    {
        $this->actingAs($this->usuarioCom('validacao.registrar'))->get('/pontos-acesso')->assertRedirect(route('dashboard'));
        $this->actingAs($this->usuarioCom('integracoes.gerenciar'))->get('/pontos-acesso')->assertOk()->assertSee('Novo ponto de acesso');
    }

    public function test_cadastra_ponto_real_em_implantacao(): void
    {
        Livewire::actingAs($this->usuarioCom('integracoes.gerenciar'))->test(PontoAcessoManagement::class)
            ->set('form.codigo', 'cancela-entrada')
            ->set('form.nome', 'Entrada Principal')
            ->set('form.tipo', 'cancela')
            ->set('form.direcao_suportada', 'entrada')
            ->call('cadastrar')
            ->assertSet('feedback.variant', 'success')
            ->assertSee('Entrada Principal');

        $ponto = PontoAcesso::query()->where('codigo', 'CANCELA-ENTRADA')->sole();
        $this->assertSame(StatusPontoAcesso::EmImplantacao, $ponto->status);
    }

    public function test_leva_a_facial_da_bancada_ao_ponto_real_e_exige_novo_teste_do_rele(): void
    {
        $terminal = $this->terminalEmHomologacao();
        $operador = $this->usuarioCom('integracoes.gerenciar', ComandosAbertura::PERMISSAO_LIBERAR);
        $testes = app(TesteReleBancada::class);

        // Relé homologado na bancada.
        foreach (range(1, 2) as $i) {
            $this->processar($testes->solicitar($terminal, 'rele:'.Str::uuid7(), $operador, 'Teste na bancada'));
        }
        $testes->registrarHomologacao($terminal, $operador, true, 'Clique ouvido na bancada');
        $this->assertNotNull($terminal->refresh()->rele_homologado_em);

        $ponto = $this->novoPonto(['codigo' => 'CANCELA-01', 'nome' => 'Entrada Principal']);
        $tela = Livewire::actingAs($operador)->test(PontoAcessoManagement::class);

        // Sem motivo, nada muda.
        $tela->call('desvincularTerminal', $terminal->id)->assertHasErrors('motivo');

        $tela->set('motivo', 'Instalação na cancela real')
            ->call('desvincularTerminal', $terminal->id)
            ->set('terminalEscolhido.'.$ponto->id, $terminal->id)
            ->call('vincularTerminal', $ponto->id)
            ->assertSet('feedback.variant', 'success');

        $terminal->refresh();
        $this->assertSame($ponto->id, $terminal->pontoVigente()->id);
        $this->assertNull($terminal->rele_homologado_em, 'Homologação da bancada não vale no ponto real.');
        $this->assertTrue(AuditoriaEvento::query()->where('action', 'equipamento_desvinculado_do_ponto')->exists());

        // No ponto real, o relé é testado de novo (contato seco) e então o terminal é ativado.
        foreach (range(1, 2) as $i) {
            $this->processar($testes->solicitar($terminal, 'rele:'.Str::uuid7(), $operador, 'Contato seco na central'));
        }
        $testes->registrarHomologacao($terminal, $operador, true, 'Cancela abriu nos dois testes');

        $tela->set('motivo', 'Contato seco validado')
            ->call('alterarSituacaoPonto', $ponto->id, 'ativo')
            ->set('motivo', 'Contato seco validado')
            ->call('alterarSituacaoTerminal', $terminal->id, 'ativo')
            ->assertSet('feedback.variant', 'success');

        $this->assertSame(StatusPontoAcesso::Ativo, $ponto->refresh()->status);
        $this->assertSame(StatusEquipamento::Ativo, $terminal->refresh()->status);
    }

    public function test_terminal_ativo_nao_e_desvinculado_e_bancada_nao_e_ativada(): void
    {
        $terminal = $this->terminalAtivo();
        $tela = Livewire::actingAs($this->usuarioCom('integracoes.gerenciar'))->test(PontoAcessoManagement::class)
            ->set('motivo', 'Troca de ponto')
            ->call('desvincularTerminal', $terminal->id)
            ->assertSet('feedback.variant', 'danger');
        $this->assertNotNull($terminal->refresh()->pontoVigente());

        $bancada = $this->terminalEmHomologacao()->pontoVigente();
        $tela->set('motivo', 'Tentativa indevida')
            ->call('alterarSituacaoPonto', $bancada->id, 'ativo')
            ->assertSet('feedback.variant', 'danger');
        $this->assertNotSame(StatusPontoAcesso::Ativo, $bancada->refresh()->status);
    }
}
