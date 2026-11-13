<?php

namespace Tests\Feature;

use App\Livewire\AccessValidation;
use App\Models\HistoricoAcesso;
use App\Models\Pessoa;
use App\Models\Veiculo;
use App\Models\VeiculoVinculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\TestCase;

/**
 * Pedido do PO (13/11/2026): na etapa Placa o porteiro informa marca, modelo
 * e cor (com sugestões), nada obrigatório; tudo vai para o cadastro da pessoa.
 */
class VeiculoNoAtendimentoTest extends TestCase
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

    public function test_placa_nova_com_marca_modelo_e_cor_vai_para_o_cadastro_da_pessoa(): void
    {
        $pessoa = Pessoa::factory()->create();

        Livewire::actingAs($this->usuarioCom('validacao.registrar'))->test(AccessValidation::class)
            ->call('selecionarPessoa', $pessoa->id)
            ->set('passo', 'placa')->set('veioDeCarro', true)
            ->set('placaInformada', 'abc1d23')
            ->set('veiculoMarca', 'Chevrolet')
            ->assertSee('Onix Plus')
            ->set('veiculoModelo', 'Onix')
            ->call('$set', 'veiculoCor', 'Prata')
            ->set('contribution', 'no')
            ->call('savePending')
            ->assertHasNoErrors();

        $veiculo = Veiculo::query()->sole();
        $this->assertSame(['ABC1D23', 'Chevrolet', 'Onix', 'Prata', 'pendente'], [$veiculo->plate_normalized, $veiculo->brand, $veiculo->model, $veiculo->color, $veiculo->status]);
        $vinculo = VeiculoVinculo::query()->sole();
        $this->assertSame([$pessoa->id, 'autorizado'], [$vinculo->pessoa_id, $vinculo->tipo]);
        $this->assertSame($veiculo->id, HistoricoAcesso::query()->sole()->veiculo_id);
    }

    public function test_nada_e_obrigatorio_alem_da_placa(): void
    {
        $pessoa = Pessoa::factory()->create();

        Livewire::actingAs($this->usuarioCom('validacao.registrar'))->test(AccessValidation::class)
            ->call('selecionarPessoa', $pessoa->id)
            ->set('passo', 'placa')->set('veioDeCarro', true)
            ->set('placaInformada', 'XYZ9A87')
            ->call('continuar')
            ->assertHasNoErrors()
            ->assertSet('passo', 'concluir');
    }

    public function test_volta_e_toca_no_cartao_traz_marca_modelo_e_cor(): void
    {
        $pessoa = Pessoa::factory()->create();
        $veiculo = Veiculo::query()->create(['plate_display' => 'VAL1D23', 'plate_normalized' => 'VAL1D23', 'country' => 'BR', 'type' => 'carro', 'brand' => 'Fiat', 'model' => 'Argo', 'color' => 'Branca', 'status' => 'ativo']);
        VeiculoVinculo::query()->create(['veiculo_id' => $veiculo->id, 'pessoa_id' => $pessoa->id, 'tipo' => 'autorizado', 'status' => 'ativo', 'started_at' => now()->subDay(), 'versao' => 1]);

        Livewire::actingAs($this->usuarioCom('validacao.registrar'))->test(AccessValidation::class)
            ->call('selecionarPessoa', $pessoa->id)
            ->set('passo', 'placa')->set('veioDeCarro', true)
            ->assertSee('Fiat · Argo · Branca')
            ->call('usarVeiculoDoCadastro', 'VAL1D23')
            ->assertSet('placaInformada', 'VAL1D23')
            ->assertSet('veiculoMarca', 'Fiat')
            ->assertSet('veiculoModelo', 'Argo')
            ->assertSet('veiculoCor', 'Branca')
            ->assertSee('confere com o cadastro');
    }

    public function test_veiculo_de_outra_pessoa_nao_muda_de_dono_e_so_completa_o_vazio(): void
    {
        $dono = Pessoa::factory()->create();
        $visitante = Pessoa::factory()->create();
        $veiculo = Veiculo::query()->create(['plate_display' => 'DON0A11', 'plate_normalized' => 'DON0A11', 'country' => 'BR', 'type' => 'carro', 'brand' => 'Toyota', 'model' => 'Corolla', 'color' => null, 'status' => 'ativo']);
        VeiculoVinculo::query()->create(['veiculo_id' => $veiculo->id, 'pessoa_id' => $dono->id, 'tipo' => 'proprietario', 'status' => 'ativo', 'started_at' => now()->subDay(), 'versao' => 1]);

        Livewire::actingAs($this->usuarioCom('validacao.registrar'))->test(AccessValidation::class)
            ->call('selecionarPessoa', $visitante->id)
            ->set('passo', 'placa')->set('veioDeCarro', true)
            ->set('placaInformada', 'DON0A11')
            ->assertSet('veiculoMarca', 'Toyota')
            ->set('veiculoMarca', 'Outra Marca')
            ->set('veiculoCor', 'Preta')
            ->set('contribution', 'no')
            ->call('savePending')
            ->assertHasNoErrors();

        $veiculo->refresh();
        $this->assertSame(['Toyota', 'Preta'], [$veiculo->brand, $veiculo->color]);
        $this->assertSame($dono->id, VeiculoVinculo::query()->whereNull('ended_at')->sole()->pessoa_id);
    }
}
