<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Implantacao;
use App\Models\Perfil;
use App\Models\Permissao;
use App\Models\User;
use App\Models\UsuarioImplantacao;
use App\Models\UsuarioPerfil;
use App\Support\ImplantacaoContext;
use App\Support\Navigation;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\UsuarioDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
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

    public function test_cash_closings_menu_is_visible_only_to_authorized_operators(): void
    {
        // AJ-008 ainda pode não estar integrado: simula sua rota com a permissão real.
        // Quando o AJ-008 entrar na main, usa a rota existente em vez de substituí-la.
        if (! Route::has('cash-register.closings')) {
            Route::get('/caixa/fechamentos', fn () => 'OK')
                ->middleware(['auth', 'permissao:caixa.proprio.gerenciar,caixa.consolidado.consultar'])
                ->name('cash-register.closings');
        }

        $authorized = $this->operador('caixa.proprio.gerenciar');
        $routes = collect(Navigation::groups($authorized)['Operação'])->pluck('route')->all();
        $this->assertContains('cash-register', $routes);
        $this->assertContains('cash-register.closings', $routes);

        $unauthorized = $this->operador('validacao.registrar');
        $unauthorizedRoutes = collect(Navigation::groups($unauthorized)['Operação'])->pluck('route')->all();
        $this->assertNotContains('cash-register.closings', $unauthorizedRoutes);
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

    public function test_denied_direct_access_keeps_the_notice_on_the_landing_page(): void
    {
        $this->withoutVite();
        $user = $this->operador('validacao.registrar');

        $this->actingAs($user)->get('/usuarios')->assertRedirect(route('home'));
        $this->get('/inicio')->assertRedirect(route('portaria'));
        $this->get('/portaria')->assertOk()->assertSee('Sem permissão para acessar esta área.');
    }

    /** Cada perfil padrão entra numa área permitida e todo item do seu menu abre sem ciclo. */
    public function test_every_default_profile_lands_on_an_allowed_page_and_every_menu_link_opens(): void
    {
        $this->withoutVite();
        $this->seed(UsuarioDemoSeeder::class);
        $expectedHome = [
            'Gestor' => 'dashboard',
            'Direção' => 'dashboard',
            'Porteiro/Caixa' => 'portaria',
            'Auditor' => 'reports',
            'Administrador' => 'portaria',
        ];

        foreach ($expectedHome as $name => $home) {
            $user = $this->userWithProfile($name);
            $this->actingAs($user)->get('/inicio')->assertRedirect(route($home));

            $routes = collect(Navigation::groups($user))->flatten(1)->pluck('route');
            $this->assertNotEmpty($routes, $name);
            $this->assertSame($name === 'Direção', $routes->all() === ['dashboard'], $name);
            $this->assertSame(in_array($name, ['Gestor', 'Direção'], true), $routes->contains('dashboard'), $name);

            foreach ($routes as $route) {
                $this->followingRedirects()->get(route($route))->assertOk();
            }

            $routes->contains('dashboard')
                ? $this->get('/dashboard')->assertOk()
                : $this->get('/dashboard')->assertRedirect(route('home'));
        }
    }

    public function test_collapsible_groups_open_only_the_current_page_group(): void
    {
        $this->withoutVite();
        $this->seed(UsuarioDemoSeeder::class);
        $this->actingAs($this->userWithProfile('Gestor'));

        $html = $this->get('/caixa')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<details class="navigation__group"\s+open\s*>\s*<summary>Operação</summary>#', $html);
        $this->assertMatchesRegularExpression('#<details class="navigation__group"\s*>\s*<summary>Cadastros</summary>#', $html);
        $this->assertStringContainsString('aria-current="page"', $html);

        // Página fora do menu: abre o primeiro grupo para não esconder tudo.
        $html = $this->get('/sessoes')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<details class="navigation__group"\s+open\s*>\s*<summary>Visão geral</summary>#', $html);
    }

    public function test_landing_page_follows_the_profiles_of_the_selected_implantacao(): void
    {
        $this->withoutVite();
        $santaRita = Implantacao::current();
        $user = $this->operador('dashboard.visualizar');
        $other = Implantacao::factory()->create(['status' => 'ativa']);

        ImplantacaoContext::executarNo($other, function () use ($user): void {
            $profile = Perfil::factory()->create();
            $permission = Permissao::query()->firstOrCreate(['chave' => 'validacao.registrar'], ['modulo' => 'teste', 'descricao' => 'Validação']);
            $profile->permissoes()->attach($permission->id, ['id' => (string) Str::uuid7(), 'implantacao_id' => Implantacao::current()->id]);
            UsuarioPerfil::factory()->for($user)->for($profile)->create();
            UsuarioImplantacao::factory()->for($user)->create(['status' => 'ativa']);
        });
        ImplantacaoContext::forgetCurrent();

        $this->actingAs($user)->get('/inicio')->assertRedirect(route('implantacao.selecionar'));

        $this->withSession(['implantacao_atual_id' => $santaRita->id])->get('/inicio')->assertRedirect(route('dashboard'));
        $this->get('/dashboard')->assertOk();

        $this->withSession(['implantacao_atual_id' => $other->id])->get('/inicio')->assertRedirect(route('portaria'));
        $this->get('/dashboard')->assertRedirect(route('home'));
        $this->get('/portaria')->assertOk()->assertDontSee('href="'.route('dashboard').'"', false);
    }

    public function test_revoked_profile_assignment_removes_modules_on_next_request(): void
    {
        $this->withoutVite();
        $user = $this->operador('validacao.registrar');
        $this->actingAs($user)->get('/portaria')->assertOk();

        $user->usuarioPerfis()->update(['ended_at' => now()->addSecond()]);
        $this->travel(2)->seconds();
        $user->usuarioPerfis()->update(['ended_at' => now()->subSecond()]);

        $this->assertSame([], Navigation::groups($user));
        $this->get('/portaria')->assertRedirect(route('home'));
        $this->get('/inicio')->assertRedirect(route('sessions'));
    }

    /** A escolha passa pelo endpoint HTTP do Livewire, não só pelo Livewire::test. */
    public function test_user_with_two_implantacoes_can_pick_one_over_http_and_lands_on_allowed_page(): void
    {
        $this->withoutVite();
        $user = $this->operador('validacao.registrar');
        $other = Implantacao::factory()->create(['status' => 'ativa']);
        ImplantacaoContext::executarNo($other, fn () => UsuarioImplantacao::factory()->for($user)->create(['status' => 'ativa']));
        ImplantacaoContext::forgetCurrent();

        $this->actingAs($user)->get('/inicio')->assertRedirect(route('implantacao.selecionar'));
        $html = $this->get('/selecionar-implantacao')->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $match);
        $snapshot = html_entity_decode($match[1]);

        // Outro componente continua barrado enquanto não houver escolha.
        $forged = json_decode($snapshot, true);
        $forged['memo']['name'] = 'dashboard';
        $this->livewireUpdate(json_encode($forged), 'setPeriod', ['7dias'])
            ->assertRedirect(route('implantacao.selecionar'));

        $this->livewireUpdate($snapshot, 'selecionar', [(string) Implantacao::query()->where('slug', 'santa-rita')->value('id')])
            ->assertOk()
            ->assertJsonPath('components.0.effects.redirect', route('home'));
        $this->get('/inicio')->assertRedirect(route('portaria'));
    }

    private function livewireUpdate(string $snapshot, string $method, array $params): TestResponse
    {
        return $this->withHeaders(['X-Livewire' => 'true'])->postJson(
            collect(Route::getRoutes()->getRoutesByName())->first(fn ($route, $name) => str_ends_with($name, 'livewire.update'))->uri(),
            ['_token' => csrf_token(), 'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => $method, 'params' => $params]]]]],
        );
    }

    private function userWithProfile(string $name): User
    {
        $user = User::factory()->create();
        UsuarioPerfil::factory()->for($user)->for(Perfil::query()->where('nome', $name)->firstOrFail())->create();
        UsuarioImplantacao::factory()->for($user)->create(['status' => 'ativa']);

        return $user;
    }
}
