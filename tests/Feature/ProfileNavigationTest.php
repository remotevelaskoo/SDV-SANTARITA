<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Perfil;
use App\Models\Implantacao;
use App\Models\Permissao;
use App\Models\UsuarioPerfil;
use App\Support\ImplantacaoContext;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Str;
use App\Support\Navigation;
use Database\Seeders\UsuarioDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\OperadorComPermissoes;
use Tests\TestCase;

class ProfileNavigationTest extends TestCase
{
    use OperadorComPermissoes, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FoundationSeeder::class);
        ImplantacaoContext::setCurrentForTesting(Implantacao::query()->where('slug', 'santa-rita')->firstOrFail());
    }

    protected function tearDown(): void
    {
        ImplantacaoContext::forgetCurrent();
        parent::tearDown();
    }

    public function test_operator_sees_only_authorized_modules_and_starts_at_portaria(): void
    {
        $this->withoutVite();
        $user = $this->operador('validacao.registrar', 'caixa.proprio.gerenciar');
        $this->actingAs($user)->get('/inicio')->assertRedirect(route('portaria'));
        $this->get('/portaria')->assertOk()
            ->assertSee('Modo Portaria')->assertSee('Caixa')
            ->assertDontSee('href="'.route('dashboard').'"', false)
            ->assertDontSee('href="'.route('users').'"', false)
            ->assertDontSee('href="'.route('equipment').'"', false)
            ->assertDontSee('Manutenção');
        $this->get('/dashboard')->assertRedirect(route('home'));
        Livewire::test(Dashboard::class)->assertForbidden();
    }

    public function test_management_permission_allows_dashboard_in_http_and_livewire(): void
    {
        $this->withoutVite();
        $user = $this->operador('dashboard.visualizar');
        $this->actingAs($user)->get('/inicio')->assertRedirect(route('dashboard'));
        $this->get('/dashboard')->assertOk();
        Livewire::test(Dashboard::class)->call('setPeriod', '7dias')->assertSet('period', '7dias');
    }

    public function test_revoking_permission_blocks_an_already_open_livewire_dashboard(): void
    {
        $user = $this->operador('dashboard.visualizar');
        $component = Livewire::actingAs($user)->test(Dashboard::class);
        $user->usuarioPerfis()->first()->perfil->permissoes()->detach();
        $component->call('setPeriod', '7dias')->assertForbidden();
    }

    public function test_consolidated_reports_are_visible_without_own_report_permission(): void
    {
        $user = $this->operador('relatorios.consolidado.consultar');
        $this->actingAs($user)->get('/inicio')->assertRedirect(route('reports'));
        $this->assertSame(['Gestão'], array_keys(Navigation::groups($user)));
    }

    public function test_account_without_modules_has_a_landing_page_without_redirect_loop(): void
    {
        $this->withoutVite();
        $user = $this->operador();
        $this->assertSame([], Navigation::groups($user));
        $this->actingAs($user)->get('/inicio')->assertRedirect(route('sessions'));
        $this->get('/sessoes')->assertOk();
    }

    public function test_inactive_profile_does_not_grant_navigation_or_dashboard(): void
    {
        $user = $this->operador('dashboard.visualizar');
        $user->usuarioPerfis()->first()->perfil->update(['status' => 'inativo']);
        $this->assertSame([], Navigation::groups($user));
        $this->assertFalse($user->hasPermission('dashboard.visualizar'));
    }

    public function test_default_dashboard_grant_is_only_for_management_and_direction(): void
    {
        $this->seed(UsuarioDemoSeeder::class);
        foreach (['Gestor', 'Direção', 'Porteiro/Caixa', 'Auditor', 'Administrador'] as $name) {
            $profile = Perfil::query()->where('nome', $name)->firstOrFail();
            $this->assertSame(in_array($name, ['Gestor', 'Direção'], true), $profile->concede('dashboard.visualizar'), $name);
        }
    }

    public function test_existing_installation_migration_restricts_grants_and_can_run_twice(): void
    {
        $this->seed(UsuarioDemoSeeder::class);
        $permission = Permissao::query()->where('chave', 'dashboard.visualizar')->firstOrFail();
        $portaria = Perfil::query()->where('nome', 'Porteiro/Caixa')->firstOrFail();
        $portaria->permissoes()->attach($permission->id, [
            'id' => (string) Str::uuid7(), 'implantacao_id' => Implantacao::current()->id,
        ]);
        $migration = require database_path('migrations/2026_11_02_100000_restrict_dashboard_to_management_profiles.php');
        $migration->up();
        $migration->up();
        $this->assertFalse($portaria->concede('dashboard.visualizar'));
        $this->assertTrue(Perfil::query()->where('nome', 'Gestor')->firstOrFail()->concede('dashboard.visualizar'));
    }

    public function test_permissions_of_another_implantacao_do_not_appear_in_navigation(): void
    {
        $user = $this->operador('validacao.registrar');
        $original = Implantacao::current();
        $other = Implantacao::factory()->create();
        ImplantacaoContext::executarNo($other, function () use ($user): void {
            $profile = Perfil::factory()->create();
            $permission = Permissao::query()->firstOrCreate(['chave' => 'dashboard.visualizar'], ['modulo' => 'dashboard', 'descricao' => 'Dashboard']);
            $profile->permissoes()->attach($permission->id, [
                'id' => (string) Str::uuid7(), 'implantacao_id' => Implantacao::current()->id,
            ]);
            UsuarioPerfil::factory()->for($user)->for($profile)->create();
        });
        $this->assertSame($original->id, Implantacao::current()->id);
        $this->assertFalse($user->hasPermission('dashboard.visualizar'));
        $this->assertArrayNotHasKey('Visão geral', Navigation::groups($user));
    }

    public function test_guest_has_no_internal_menu(): void
    {
        $this->assertSame([], Navigation::groups(null));
        $this->get('/inicio')->assertRedirect(route('login'));
    }
}
