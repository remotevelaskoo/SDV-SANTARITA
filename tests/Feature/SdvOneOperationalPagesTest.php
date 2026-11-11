<?php

namespace Tests\Feature;

use App\Livewire\PreRegistrationQueue;
use App\Models\Implantacao;
use App\Models\PreRegistration;
use App\Support\ImplantacaoContext;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\OperadorComPermissoes;
use Tests\TestCase;

class SdvOneOperationalPagesTest extends TestCase
{
    use OperadorComPermissoes, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FoundationSeeder::class);
        ImplantacaoContext::setCurrentForTesting(
            Implantacao::query()->where('slug', 'santa-rita')->firstOrFail()
        );
    }

    protected function tearDown(): void
    {
        ImplantacaoContext::forgetCurrent();
        parent::tearDown();
    }

    public function test_validacao_nova_preserva_etapas_e_acoes_do_atendimento(): void
    {
        $this->withoutVite();
        $usuario = $this->operador('validacao.registrar', 'pessoas.gerenciar');

        $this->actingAs($usuario)->get(route('validation'))
            ->assertOk()
            ->assertSee('sdv-one-validation', false)
            ->assertSee('Validação de entrada')
            ->assertSee('Identificação da pessoa')
            ->assertSee('Veículo e leitura da placa')
            ->assertSee('Contribuição / taxa de acesso')
            ->assertSee('Validar e liberar')
            ->assertSee('Pessoa não encontrada? Cadastro rápido')
            ->assertSee('Sem acionamento nesta tela')
            ->assertDontSee('Conectada');
    }

    public function test_fila_nova_mantem_filtros_busca_e_analise(): void
    {
        $this->withoutVite();
        $usuario = $this->operador('pre-registro.analisar');
        $registro = PreRegistration::factory()->create([
            'name' => 'Visitante de Teste',
            'status' => 'aguardando',
        ]);

        $this->actingAs($usuario)->get(route('pre-registrations'))
            ->assertOk()
            ->assertSee('sdv-one-pre-registration-queue', false)
            ->assertSee('Visitante de Teste')
            ->assertSee('Aguardando análise')
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('href="'.route('pre-registrations.review', $registro->id).'"', false)
            ->assertSee('filtros de tipo e período', false);

        Livewire::actingAs($usuario)->test(PreRegistrationQueue::class)
            ->call('setStatusFilter', 'todos')
            ->assertSet('statusFilter', 'todos')
            ->set('search', 'Visitante de Teste')
            ->assertSee('Visitante de Teste');
    }

    public function test_analise_nova_mantem_pendencias_documentos_e_decisoes(): void
    {
        $this->withoutVite();
        $usuario = $this->operador('pre-registro.analisar', 'pre-registro.aprovar');
        $registro = PreRegistration::factory()->create([
            'status' => 'aguardando',
            'name' => 'Conferência Visual',
        ]);

        $this->actingAs($usuario)->get(route('pre-registrations.review', $registro->id))
            ->assertOk()
            ->assertSee('sdv-one-pre-registration-review', false)
            ->assertSee('Conferência Visual')
            ->assertSee('Análise de pré-cadastro')
            ->assertSee('Conferência visual protegida')
            ->assertSee('Dados essenciais')
            ->assertSee('Aprovar pré-cadastro')
            ->assertSee('Voltar à fila de pré-cadastros');
    }
}
