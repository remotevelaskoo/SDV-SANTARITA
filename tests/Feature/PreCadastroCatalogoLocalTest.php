<?php

namespace Tests\Feature;

use App\Livewire\PreRegistrationReview;
use App\Livewire\PublicPreRegistration;
use App\Models\Implantacao;
use App\Models\PreRegistration;
use App\Services\PreCadastro\CatalogoDestinos;
use App\Support\ImplantacaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\DestinosDeTeste;
use Tests\Support\OperadorComPermissoes;
use Tests\TestCase;

/**
 * Revisão do PO no PR #88 (docs/023 §12): uma só lista de imóveis (cadastro local),
 * prestador sem responsável e data prevista da visita no formulário local.
 */
class PreCadastroCatalogoLocalTest extends TestCase
{
    use OperadorComPermissoes, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        Storage::fake('private-files');
        DestinosDeTeste::criar(['Bloco B — Apto 304' => 'Mariana Souza', 'Bloco C — Loja 1' => null]);
    }

    protected function tearDown(): void
    {
        ImplantacaoContext::forgetCurrent();
        parent::tearDown();
    }

    public function test_visitante_so_ve_imoveis_com_responsavel_e_prestador_ve_todos(): void
    {
        $catalogo = app(CatalogoDestinos::class);
        $this->assertSame(['Bloco B — Apto 304'], $catalogo->opcoes('visitante'));
        $this->assertSame(['Bloco B — Apto 304', 'Bloco C — Loja 1'], $catalogo->opcoes('prestador'));
        $this->assertSame([], $catalogo->opcoes('turista'));

        // Imóvel de outra implantação nunca aparece.
        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        $this->assertSame([], app(CatalogoDestinos::class)->opcoes('prestador'));
    }

    public function test_prestador_no_formulario_local_nao_grava_responsavel_e_usa_a_data_da_visita(): void
    {
        $visita = today()->addDays(5)->toDateString();
        $this->formulario('prestador', 'Bloco B — Apto 304', $visita)->assertSet('submitted', true);

        $registro = PreRegistration::query()->sole();
        $this->assertSame('prestador', $registro->access_type);
        $this->assertNull($registro->responsible_name);
        $this->assertSame($visita.' 00:00', $registro->period_start->format('Y-m-d H:i'));
        $this->assertSame(today()->addDays(6)->toDateString().' 00:00', $registro->period_end->format('Y-m-d H:i'));
    }

    public function test_visitante_leva_o_responsavel_principal_do_cadastro_local(): void
    {
        $this->formulario('visitante', 'Bloco B — Apto 304')->assertSet('submitted', true);
        $this->assertSame('Mariana Souza', PreRegistration::query()->sole()->responsible_name);

        $this->formulario('visitante', 'Bloco C — Loja 1')
            ->assertHasErrors(['destinationProperty'])
            ->assertSet('submitted', false);
    }

    public function test_data_da_visita_fica_entre_hoje_e_trinta_dias(): void
    {
        $this->formulario('turista', null, today()->subDay()->toDateString())->assertHasErrors(['visitDate']);
        $this->formulario('turista', null, today()->addDays(31)->toDateString())->assertHasErrors(['visitDate']);
        $this->formulario('turista', null, today()->addDays(30)->toDateString())->assertSet('submitted', true);
        $this->assertDatabaseCount('autorizacoes_acesso', 0);
    }

    public function test_correcao_de_prestador_na_analise_remove_o_responsavel_antigo(): void
    {
        $registro = PreRegistration::factory()->create(['access_type' => 'prestador', 'destination_label' => 'Bloco B — Apto 304', 'responsible_name' => 'Responsável antigo']);
        $this->actingAs($this->operador('pre-registro.analisar', 'pre-registro.editar'));

        Livewire::test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->call('beginEdit')
            ->assertDontSee('id="edit-responsible"', false)
            ->set('editReason', 'Prestador não tem responsável.')
            ->call('saveEdit')
            ->assertHasNoErrors();

        $this->assertNull($registro->fresh()->responsible_name);
    }

    private function formulario(string $tipo, ?string $imovel, ?string $visita = null): Testable
    {
        $teste = Livewire::test(PublicPreRegistration::class)
            ->call('start')
            ->set('name', 'Pessoa Sintética')
            ->set('cpf', '123.456.789-09')
            ->set('accessType', $tipo)
            ->set('documentType', 'cnh')
            ->set('documentFile', UploadedFile::fake()->image('frente.jpg', 900, 600))
            ->set('selfieFile', UploadedFile::fake()->image('selfie.jpg', 480, 640))
            ->set('privacyAccepted', true);
        if ($imovel !== null) {
            $teste->set('destinationProperty', $imovel);
        }
        if ($visita !== null) {
            $teste->set('visitDate', $visita);
        }

        return $teste->call('submit');
    }
}
