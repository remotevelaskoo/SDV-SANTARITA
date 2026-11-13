<?php

namespace Tests\Feature;

use App\Livewire\PessoaConsulta;
use App\Models\AutorizacaoAcesso;
use App\Models\Imovel;
use App\Models\Pessoa;
use App\Models\PessoaDocumento;
use App\Models\User;
use App\Models\Veiculo;
use App\Models\VeiculoVinculo;
use App\Models\Vinculo;
use App\Services\PrivateFileService;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\Support\ImagensPessoaSinteticas;
use Tests\TestCase;

/** Consulta de pessoas em Cadastros → Pessoas (pedido do PO de 13/11/2026). */
class PessoaConsultaTest extends TestCase
{
    use MontaCenarioIntegracao, RefreshDatabase;

    private User $operador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararIntegracao();
        Storage::fake(PrivateFileService::DISK);
        $this->operador = $this->usuarioCom('pessoas.gerenciar');
    }

    protected function tearDown(): void
    {
        $this->encerrarIntegracao();
        parent::tearDown();
    }

    public function test_menu_pessoas_abre_a_consulta_e_quem_nao_tem_permissao_nao_entra(): void
    {
        $this->actingAs($this->operador)->get(route('people'))->assertOk()->assertSee('Consulta de pessoas')->assertSee('Nova pessoa');

        $itens = collect(Navigation::groups($this->operador))->flatten(1)->pluck('route');
        $this->assertTrue($itens->contains('people'));
        $this->assertFalse($itens->contains('people.create'), 'Nova pessoa fica como botão dentro da consulta.');

        $this->actingAs($this->usuarioCom('validacao.registrar'))->get(route('people'))->assertRedirect();
    }

    public function test_busca_por_nome_cpf_completo_placa_e_imovel(): void
    {
        $bianca = Pessoa::factory()->create(['nome' => 'Bianca Moretti']);
        $outra = Pessoa::factory()->create(['nome' => 'Carlos Souza']);
        PessoaDocumento::factory()->for($bianca, 'pessoa')->create(['valor_normalizado' => '52998224725', 'valor_apresentacao' => '529.982.247-25']);
        $veiculo = Veiculo::factory()->create(['plate_display' => 'GHI7J89', 'plate_normalized' => 'GHI7J89']);
        VeiculoVinculo::factory()->create(['veiculo_id' => $veiculo->id, 'pessoa_id' => $bianca->id]);
        $imovel = Imovel::factory()->create();
        Vinculo::factory()->for($outra, 'pessoa')->for($imovel, 'imovel')->create(['ended_at' => null, 'tipo' => 'morador']);

        $tela = Livewire::actingAs($this->operador)->test(PessoaConsulta::class);
        $nomes = fn () => collect($tela->instance()->pessoas->items())->pluck('nome')->all();

        $this->assertSame(['Bianca Moretti', 'Carlos Souza'], $nomes());
        $tela->set('busca', 'moretti');
        $this->assertSame(['Bianca Moretti'], $nomes());
        $tela->set('busca', '529.982.247-25');
        $this->assertSame(['Bianca Moretti'], $nomes());
        $tela->set('busca', '529982');
        $this->assertSame([], $nomes(), 'CPF incompleto não lista ninguém.');
        $tela->set('busca', 'ghi-7j89');
        $this->assertSame(['Bianca Moretti'], $nomes());
        $tela->set('busca', $imovel->codigo);
        $this->assertSame(['Carlos Souza'], $nomes());
    }

    public function test_filtros_de_tipo_situacao_vigente_e_sem_foto(): void
    {
        $morador = Pessoa::factory()->create(['nome' => 'Ana Moradora']);
        Vinculo::factory()->for($morador, 'pessoa')->for(Imovel::factory()->create(), 'imovel')->create(['ended_at' => null, 'tipo' => 'morador', 'status' => 'ativo']);
        app(PrivateFileService::class)->storePessoaFoto($morador, UploadedFile::fake()->createWithContent('rosto.jpg', ImagensPessoaSinteticas::boa()));

        $turista = Pessoa::factory()->create(['nome' => 'Beto Turista']);
        AutorizacaoAcesso::query()->create([
            'pessoa_id' => $turista->id, 'tipo' => AutorizacaoAcesso::TIPO_TURISTA, 'inicio' => now()->subHour(), 'fim' => now()->addHours(23), 'status' => 'ativa',
        ]);

        Pessoa::factory()->create(['nome' => 'Caio Bloqueado', 'status' => 'bloqueado']);

        $tela = Livewire::actingAs($this->operador)->test(PessoaConsulta::class);
        $nomes = fn () => collect($tela->instance()->pessoas->items())->pluck('nome')->all();

        $tela->set('filtroTipo', 'morador');
        $this->assertSame(['Ana Moradora'], $nomes());
        $tela->set('filtroTipo', 'turista');
        $this->assertSame(['Beto Turista'], $nomes());
        $tela->set('filtroTipo', 'sem_vinculo');
        $this->assertSame(['Caio Bloqueado'], $nomes());

        $tela->call('limparFiltros')->set('filtroSituacao', 'bloqueado');
        $this->assertSame(['Caio Bloqueado'], $nomes());

        $tela->call('limparFiltros')->set('soVigentes', true);
        $this->assertSame(['Ana Moradora', 'Beto Turista'], $nomes());

        $tela->call('limparFiltros')->set('soSemFoto', true);
        $this->assertSame(['Beto Turista', 'Caio Bloqueado'], $nomes());
    }

    public function test_ficha_mostra_vinculos_autorizacoes_veiculos_e_permite_corrigir_dados(): void
    {
        $pessoa = Pessoa::factory()->create(['nome' => 'Bianca Moretti']);
        Vinculo::factory()->for($pessoa, 'pessoa')->for(Imovel::factory()->create(), 'imovel')->create(['ended_at' => null, 'tipo' => 'proprietario']);
        $veiculo = Veiculo::factory()->create(['plate_display' => 'GHI7J89', 'plate_normalized' => 'GHI7J89']);
        VeiculoVinculo::factory()->create(['veiculo_id' => $veiculo->id, 'pessoa_id' => $pessoa->id]);

        Livewire::actingAs($this->operador)->test(PessoaConsulta::class)
            ->call('abrir', $pessoa->id)
            ->assertSee('Vínculos com imóveis')
            ->assertSee('Proprietário')
            ->assertSee('GHI7J89')
            ->assertSee('Nenhum atendimento registrado.')
            ->call('editarIdentificacao')
            ->set('identNome', 'Bianca Moretti Alves')
            ->set('identTelefone', '(12) 99833-4455')
            ->call('salvarIdentificacao')
            ->assertHasNoErrors()
            ->assertSee('Dados corrigidos')
            ->assertSee('Bianca Moretti Alves')
            ->call('voltarParaLista')
            ->assertSee('Consulta de pessoas');

        $this->assertSame('Bianca Moretti Alves', $pessoa->refresh()->nome);
    }

    public function test_sem_permissao_de_gerenciar_nao_edita(): void
    {
        $pessoa = Pessoa::factory()->create();

        Livewire::actingAs($this->usuarioCom('validacao.registrar'))->test(PessoaConsulta::class)
            ->set('currentPessoaId', $pessoa->id)
            ->call('editarIdentificacao')
            ->assertForbidden();
    }
}
