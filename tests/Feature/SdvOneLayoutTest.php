<?php

namespace Tests\Feature;

use App\Models\Implantacao;
use App\Support\ImplantacaoContext;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\OperadorComPermissoes;
use Tests\TestCase;

class SdvOneLayoutTest extends TestCase
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

    public function test_modo_portaria_mantem_fluxos_com_o_novo_layout(): void
    {
        $this->withoutVite();
        $usuario = $this->operador('validacao.registrar', 'caixa.proprio.gerenciar');

        $this->actingAs($usuario)->get(route('portaria'))
            ->assertOk()
            ->assertSee('Soluções do Vale')
            ->assertSee('sdv-one-hero', false)
            ->assertSee('Validar entrada')
            ->assertSee('Cadastro rápido')
            ->assertSee('Entradas hoje')
            ->assertSee('Saídas hoje')
            ->assertSee('Últimas movimentações')
            ->assertSee('Conferência visual protegida')
            ->assertSee('href="'.route('validation').'"', false)
            ->assertSee('href="'.route('cash-register').'"', false);
    }

    public function test_sem_permissao_continua_sem_acesso_ao_modo_portaria(): void
    {
        $this->withoutVite();
        $usuario = $this->operador('caixa.proprio.gerenciar');

        $this->actingAs($usuario)->get(route('portaria'))
            ->assertRedirect(route('home'));
    }
}
