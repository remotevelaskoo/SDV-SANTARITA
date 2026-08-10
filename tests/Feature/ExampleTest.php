<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Livewire\Login;
use Livewire\Livewire;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_home_page_redirects_to_the_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/entrar');
    }

    public function test_the_login_renders_the_demo_access(): void
    {
        $this->withoutVite();

        $response = $this->get('/entrar');

        $response
            ->assertOk()
            ->assertSee('Entrar no sistema')
            ->assertSee('Ambiente de demonstração')
            ->assertSee('portaria')
            ->assertSee('sdv2026');
    }

    public function test_the_demo_login_rejects_invalid_credentials(): void
    {
        Livewire::test(Login::class)
            ->set('identification', 'pessoa-desconhecida')
            ->set('password', 'senha-errada')
            ->call('login')
            ->assertHasErrors(['credentials'])
            ->assertSee('Identificação ou senha inválida.');
    }

    public function test_the_demo_login_opens_the_dashboard(): void
    {
        Livewire::test(Login::class)
            ->call('useDemoAccount')
            ->assertSet('identification', 'portaria')
            ->assertSet('password', 'sdv2026')
            ->call('login')
            ->assertRedirect(route('dashboard'));
    }

    public function test_the_dashboard_renders_the_approved_visual_structure(): void
    {
        $this->withoutVite();

        $response = $this->get('/dashboard');

        $response
            ->assertOk()
            ->assertSee('Dashboard operacional')
            ->assertSee('Pessoas cadastradas')
            ->assertSee('4.182')
            ->assertSee('Acessos recentes')
            ->assertSee('Entradas e saídas')
            ->assertSee('Monitoramento de Câmeras');
    }

    public function test_the_local_component_catalog_renders_the_shared_patterns(): void
    {
        $this->withoutVite();

        $response = $this->get('/componentes');

        $response
            ->assertOk()
            ->assertSee('Componentes compartilhados')
            ->assertSee('Botões e grupos de ações')
            ->assertSee('Campos de formulário')
            ->assertSee('Seleções e escolhas')
            ->assertSee('Navegação e etapas')
            ->assertSee('Situações e avisos')
            ->assertSee('Cartões e ausência de dados')
            ->assertSee('Tabela responsiva')
            ->assertSee('Carregamento, progresso e erro')
            ->assertSee('Janela e painel lateral')
            ->assertSee('Formulários avançados')
            ->assertSee('Informações operacionais')
            ->assertSee('Identificação, vínculos e equipamentos')
            ->assertSee('Decisão, contribuição, caixa e protocolo')
            ->assertSee('ui-button--primary', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('role="switch"', false)
            ->assertSee('role="tablist"', false)
            ->assertSee('aria-current="step"', false)
            ->assertSee('aria-label="Paginação"', false)
            ->assertSee('class="ui-dialog"', false)
            ->assertSee('class="ui-drawer"', false)
            ->assertSee('aria-autocomplete="list"', false)
            ->assertSee('class="ui-toast ui-toast--success"', false)
            ->assertSee('class="ui-activity-list"', false)
            ->assertSee('class="ui-metric"', false)
            ->assertSee('class="ui-access-types"', false)
            ->assertSee('class="ui-person-summary"', false)
            ->assertSee('class="ui-link-panel"', false)
            ->assertSee('class="ui-vehicle-card"', false)
            ->assertSee('class="ui-lpr is-match"', false)
            ->assertSee('class="ui-access-decision"', false)
            ->assertSee('class="ui-contribution"', false)
            ->assertSee('class="ui-cash-summary"', false)
            ->assertSee('class="ui-protocol"', false)
            ->assertSee('ui-responsive-table__desktop', false)
            ->assertSee('aria-valuenow="68"', false);
    }

    public function test_the_dashboard_period_and_camera_controls_are_interactive(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSet('period', 'hoje')
            ->call('setPeriod', '7dias')
            ->assertSet('period', '7dias')
            ->assertSet('cameraStatus.cam-01', true)
            ->call('toggleCamera', 'cam-01')
            ->assertSet('cameraStatus.cam-01', false)
            ->assertSee('SEM SINAL');
    }
}
