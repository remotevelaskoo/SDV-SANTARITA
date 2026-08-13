<?php

namespace Tests\Feature;

use App\Livewire\Reports;
use App\Models\CaixaMovimentacao;
use App\Models\CaixaTurno;
use App\Models\HistoricoAcesso;
use App\Models\Implantacao;
use App\Models\Perfil;
use App\Models\Permissao;
use App\Models\User;
use App\Models\UsuarioPerfil;
use App\Support\ImplantacaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
    }

    protected function tearDown(): void
    {
        ImplantacaoContext::forgetCurrent();
        parent::tearDown();
    }

    public function test_guest_cannot_access_reports(): void
    {
        $this->get('/relatorios')->assertRedirect(route('login'));
    }

    public function test_user_without_report_permission_is_redirected(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/relatorios')->assertRedirect(route('dashboard'));
    }

    public function test_own_report_only_shows_records_created_by_authenticated_operator(): void
    {
        $operator = $this->userWithPermission('relatorios.proprio.consultar');
        $other = User::factory()->create();

        HistoricoAcesso::factory()->create(['operator_id' => $operator->id, 'protocol' => 'MEU-ACESSO']);
        HistoricoAcesso::factory()->create(['operator_id' => $other->id, 'protocol' => 'OUTRO-ACESSO']);

        Livewire::actingAs($operator)
            ->test(Reports::class)
            ->assertSee('MEU-ACESSO')
            ->assertDontSee('OUTRO-ACESSO')
            ->assertSee('somente operações registradas por você');
    }

    public function test_consolidated_report_shows_records_from_all_operators(): void
    {
        $auditor = $this->userWithPermission('relatorios.consolidado.consultar');
        $operatorA = User::factory()->create();
        $operatorB = User::factory()->create();

        HistoricoAcesso::factory()->create(['operator_id' => $operatorA->id, 'protocol' => 'ACESSO-A']);
        HistoricoAcesso::factory()->create(['operator_id' => $operatorB->id, 'protocol' => 'ACESSO-B']);

        Livewire::actingAs($auditor)
            ->test(Reports::class)
            ->assertSee('ACESSO-A')
            ->assertSee('ACESSO-B')
            ->assertSee('dados consolidados');
    }

    public function test_cash_report_uses_real_movements_and_respects_own_scope(): void
    {
        $operator = $this->userWithPermission('relatorios.proprio.consultar');
        $other = User::factory()->create();
        $turno = CaixaTurno::factory()->create(['operator_id' => $operator->id]);

        CaixaMovimentacao::factory()->for($turno, 'caixaTurno')->create([
            'operator_id' => $operator->id,
            'description' => 'Contribuição própria',
            'amount' => 25,
        ]);
        CaixaMovimentacao::factory()->for($turno, 'caixaTurno')->create([
            'operator_id' => $other->id,
            'description' => 'Movimento de outro operador',
            'amount' => 50,
        ]);

        Livewire::actingAs($operator)
            ->test(Reports::class)
            ->set('reportType', 'caixa')
            ->assertSee('Contribuição própria')
            ->assertDontSee('Movimento de outro operador')
            ->assertSee('25,00');
    }

    public function test_filters_are_applied_to_the_access_report(): void
    {
        $auditor = $this->userWithPermission('relatorios.consolidado.consultar');

        HistoricoAcesso::factory()->create(['resultado' => 'liberado', 'protocol' => 'LIBERADO-01']);
        HistoricoAcesso::factory()->create(['resultado' => 'negado', 'protocol' => 'NEGADO-01']);

        Livewire::actingAs($auditor)
            ->test(Reports::class)
            ->set('resultFilter', 'negado')
            ->assertSee('NEGADO-01')
            ->assertDontSee('LIBERADO-01');
    }

    public function test_csv_export_is_available_to_an_authorized_user(): void
    {
        $operator = $this->userWithPermission('relatorios.proprio.consultar');

        HistoricoAcesso::factory()->create(['operator_id' => $operator->id]);

        Livewire::actingAs($operator)
            ->test(Reports::class)
            ->call('exportCsv')
            ->assertFileDownloaded('relatorio-acessos-'.now()->startOfMonth()->toDateString().'-a-'.now()->toDateString().'.csv');
    }

    private function userWithPermission(string $key): User
    {
        $user = User::factory()->create();
        $permission = Permissao::factory()->create(['chave' => $key]);
        $profile = Perfil::factory()->create();
        $profile->permissoes()->attach($permission->id, [
            'id' => (string) Str::uuid7(),
            'implantacao_id' => ImplantacaoContext::current()->id,
        ]);
        UsuarioPerfil::factory()->for($user)->for($profile)->create();

        return $user;
    }
}
