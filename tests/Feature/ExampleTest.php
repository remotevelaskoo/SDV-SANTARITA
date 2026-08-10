<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use Livewire\Livewire;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_home_page_redirects_to_the_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/dashboard');
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
