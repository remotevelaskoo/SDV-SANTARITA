<?php

namespace Tests\Feature;

use App\Livewire\BuscaGlobal;
use App\Models\HistoricoAcesso;
use App\Models\Imovel;
use App\Models\Pessoa;
use App\Models\PessoaDocumento;
use App\Models\PreRegistration;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\TestCase;

/**
 * Busca global do cabeçalho (escopo aprovado pelo PO em 13/11/2026): pessoas,
 * placas, imóveis, protocolos e pré-cadastros, cada grupo só com a permissão
 * do módulo, em lista suspensa com até 5 por grupo.
 */
class BuscaGlobalTest extends TestCase
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

    private function todasAsPermissoes()
    {
        return $this->usuarioCom('pessoas.gerenciar', 'veiculos.consultar', 'imoveis.consultar', 'validacao.registrar', 'pre-registro.analisar');
    }

    public function test_acha_pessoa_por_nome_e_cpf_completo_com_cpf_mascarado(): void
    {
        $pessoa = Pessoa::factory()->create(['nome' => 'Gabriel Sintético']);
        PessoaDocumento::factory()->for($pessoa, 'pessoa')->create(['valor_normalizado' => '52998224725', 'valor_apresentacao' => '529.982.247-25']);

        $tela = Livewire::actingAs($this->todasAsPermissoes())->test(BuscaGlobal::class)
            ->set('termo', 'gabriel')
            ->assertSee('Pessoas')
            ->assertSee(route('people', ['pessoa' => $pessoa->id]), false)
            ->assertDontSee('529.982.247-25');

        $tela->set('termo', '529.982.247-25')->assertSee('Gabriel');
        $tela->set('termo', '529982')->assertDontSee('Gabriel', false);
    }

    public function test_acha_placa_imovel_protocolo_e_pre_cadastro(): void
    {
        Veiculo::query()->create(['plate_display' => 'ABC1D23', 'plate_normalized' => 'ABC1D23', 'country' => 'BR', 'type' => 'carro', 'brand' => 'Fiat', 'model' => 'Argo', 'color' => 'Branca', 'status' => 'ativo']);
        Imovel::factory()->create(['codigo' => 'SRA-A-102']);
        HistoricoAcesso::query()->create(['protocol' => 'SRA-20261113-ABC123', 'tipo' => 'entrada', 'resultado' => 'liberado', 'ponto_acesso' => 'Portaria', 'occurred_at' => now()]);
        $pre = PreRegistration::factory()->tourist()->create(['name' => 'Turista Gabriela']);

        $tela = Livewire::actingAs($this->todasAsPermissoes())->test(BuscaGlobal::class);

        $tela->set('termo', 'abc-1d23')->assertSee('Veículos')->assertSee('Fiat · Argo · Branca')->assertSee(route('vehicles', ['q' => 'ABC1D23']), false);
        $tela->set('termo', 'a-102')->assertSee('Imóveis')->assertSee('SRA-A-102');
        $tela->set('termo', 'sra-20261113')->assertSee('Protocolos de atendimento')->assertSee('SRA-20261113-ABC123');
        $tela->set('termo', 'Gabriela')->assertSee('Pré-cadastros')->assertSee(route('pre-registrations.review', $pre->id), false);
    }

    public function test_cada_grupo_so_aparece_com_a_permissao_do_modulo(): void
    {
        Pessoa::factory()->create(['nome' => 'Gabriel Sintético']);
        Veiculo::query()->create(['plate_display' => 'GAB1A23', 'plate_normalized' => 'GAB1A23', 'country' => 'BR', 'type' => 'carro', 'status' => 'ativo']);

        Livewire::actingAs($this->usuarioCom('veiculos.consultar'))->test(BuscaGlobal::class)
            ->set('termo', 'gab')
            ->assertSee('GAB1A23')
            ->assertDontSee('Gabriel Sintético');
    }

    public function test_limita_a_cinco_por_grupo_com_ver_todos_e_avisa_quando_nao_acha(): void
    {
        foreach (range(1, 7) as $n) {
            Veiculo::query()->create(['plate_display' => "XYZ{$n}A00", 'plate_normalized' => "XYZ{$n}A00", 'country' => 'BR', 'type' => 'carro', 'status' => 'ativo']);
        }

        $tela = Livewire::actingAs($this->todasAsPermissoes())->test(BuscaGlobal::class)->set('termo', 'xyz');
        $veiculos = collect($tela->instance()->grupos)->firstWhere('chave', 'veiculos');
        $this->assertCount(5, $veiculos['itens']);
        $this->assertTrue($veiculos['mais']);
        $tela->assertSee('Ver todos');

        $tela->set('termo', 'nadaassim')->assertSee('Nada encontrado');
        $tela->set('termo', 'x')->assertDontSee('Nada encontrado');
    }

    public function test_destaque_do_termo_nao_abre_brecha_para_html(): void
    {
        Pessoa::factory()->create(['nome' => '<b>Ana</b> Sintética']);

        Livewire::actingAs($this->todasAsPermissoes())->test(BuscaGlobal::class)
            ->set('termo', 'ana')
            ->assertSee('<mark>Ana</mark>', false)
            ->assertDontSee('<b>', false);
    }

    public function test_modulos_abrem_ja_filtrados_pelo_q(): void
    {
        $this->withoutVite();
        $usuario = $this->todasAsPermissoes();
        Veiculo::query()->create(['plate_display' => 'QQQ1A11', 'plate_normalized' => 'QQQ1A11', 'country' => 'BR', 'type' => 'carro', 'status' => 'ativo']);

        $this->actingAs($usuario)->get(route('vehicles', ['q' => 'QQQ1A11']))->assertOk()->assertSee('QQQ1A11');
    }
}
