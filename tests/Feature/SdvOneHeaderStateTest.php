<?php

namespace Tests\Feature;

use App\Models\CaixaTurno;
use App\Models\Implantacao;
use App\Models\PreRegistration;
use App\Support\ImplantacaoContext;
use App\Support\OperationalHeaderState;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\OperadorComPermissoes;
use Tests\TestCase;

class SdvOneHeaderStateTest extends TestCase
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

    public function test_cabecalho_nao_exibe_caixa_de_outro_operador_sem_permissao(): void
    {
        $this->withoutVite();
        $operador = $this->operador('validacao.registrar');

        $this->actingAs($operador)->get(route('portaria'))
            ->assertOk()
            ->assertDontSee('data-sdv-cash-status', false)
            ->assertDontSee('notification-dot', false)
            ->assertDontSee('Caixa 01 aberto às 13:00')
            ->assertDontSee('Vínculo de inquilino encerrado')
            ->assertSee('Buscar pessoa, CPF, placa, imóvel ou protocolo');
    }

    public function test_status_do_caixa_prorio_nao_usa_o_caixa_de_outro_operador(): void
    {
        $operador = $this->operador('validacao.registrar', 'caixa.proprio.gerenciar');
        $outroOperador = $this->operador('validacao.registrar', 'caixa.proprio.gerenciar');
        $abertura = now()->subHour()->startOfMinute();

        CaixaTurno::factory()->create([
            'operator_id' => $operador->id,
            'terminal' => 'Caixa próprio — Guarita',
            'opened_at' => $abertura,
        ]);
        CaixaTurno::factory()->create([
            'operator_id' => $outroOperador->id,
            'terminal' => 'Caixa de outro operador',
            'opened_at' => now()->subMinute(),
        ]);

        $estado = OperationalHeaderState::forUser($operador);

        $this->assertSame([
            'label' => 'Caixa próprio — Guarita aberto às '.$abertura->format('H:i'),
            'open' => true,
        ], $estado['cash']);
    }

    public function test_cabecalho_mostra_status_fechado_quando_nao_existe_caixa_proprio(): void
    {
        $operador = $this->operador('validacao.registrar', 'caixa.proprio.gerenciar');

        $this->assertSame([
            'label' => 'Seu caixa está fechado',
            'open' => false,
        ], OperationalHeaderState::forUser($operador)['cash']);
    }

    public function test_notificacoes_consultam_apenas_pendencias_reais_para_perfil_autorizado(): void
    {
        $this->withoutVite();
        $usuario = $this->operador('validacao.registrar', 'pre-registro.analisar');
        PreRegistration::factory()->create(['status' => 'aguardando']);
        PreRegistration::factory()->withStatus('aprovado')->create();

        $this->assertSame(1, OperationalHeaderState::forUser($usuario)['pendingPreRegistrations']);

        $this->actingAs($usuario)->get(route('portaria'))
            ->assertOk()
            ->assertSee('1 pré-cadastro aguardando análise')
            ->assertSee('href="'.route('pre-registrations').'"', false)
            ->assertSee('notification-dot', false);
    }

    public function test_notificacoes_ficam_restritas_ao_perfil_e_implantacao(): void
    {
        $usuario = $this->operador('validacao.registrar');
        $this->assertNull(OperationalHeaderState::forUser($usuario)['pendingPreRegistrations']);

        $autorizado = $this->operador('validacao.registrar', 'pre-registro.analisar');
        $outra = Implantacao::factory()->create();

        ImplantacaoContext::executarNo($outra, function (): void {
            PreRegistration::factory()->create(['status' => 'aguardando']);
        });

        $this->assertSame(0, OperationalHeaderState::forUser($autorizado)['pendingPreRegistrations']);
    }
}
