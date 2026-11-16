<?php

namespace Tests\Feature;

use App\Livewire\CashRegister;
use App\Livewire\CompanyManagement;
use App\Livewire\Login;
use App\Livewire\PropertyManagement;
use App\Livewire\ResetPassword;
use App\Livewire\VehicleManagement;
use App\Models\CaixaMovimentacao;
use App\Models\CaixaTurno;
use App\Models\Empresa;
use App\Models\Imovel;
use App\Models\Implantacao;
use App\Models\Perfil;
use App\Models\Permissao;
use App\Models\User;
use App\Models\UsuarioPerfil;
use App\Models\Veiculo;
use App\Support\ImplantacaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SegurancaOperacionalTest extends TestCase
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

    private function operator(string ...$permissions): User
    {
        $user = User::factory()->create();
        $profile = Perfil::factory()->create();

        foreach ($permissions as $key) {
            $permission = Permissao::query()->firstOrCreate(
                ['chave' => $key],
                ['modulo' => 'teste', 'descricao' => $key]
            );

            $profile->permissoes()->attach($permission->id, [
                'id' => (string) Str::uuid7(),
                'implantacao_id' => ImplantacaoContext::current()->id,
            ]);
        }

        UsuarioPerfil::factory()->for($user)->for($profile)->create();

        return $user;
    }

    public function test_read_only_users_cannot_open_write_forms_or_change_records(): void
    {
        $viewer = $this->operator('imoveis.consultar', 'veiculos.consultar', 'empresas.consultar');
        $property = Imovel::factory()->create(['status' => 'ativo']);
        $vehicle = Veiculo::factory()->create(['status' => 'ativo']);
        $company = Empresa::factory()->create(['status' => 'ativo']);

        Livewire::actingAs($viewer)->test(PropertyManagement::class)->call('createProperty')->assertStatus(403);
        Livewire::actingAs($viewer)->test(PropertyManagement::class)->call('editProperty', $property->id)->assertStatus(403);
        Livewire::actingAs($viewer)->test(PropertyManagement::class)->call('saveDraft')->assertStatus(403);
        Livewire::actingAs($viewer)->test(PropertyManagement::class)->call('saveProperty')->assertStatus(403);
        Livewire::actingAs($viewer)->test(PropertyManagement::class)->set('selectedPropertyId', $property->id)->call('togglePropertyBlock')->assertStatus(403);

        Livewire::actingAs($viewer)->test(VehicleManagement::class)->call('createVehicle')->assertStatus(403);
        Livewire::actingAs($viewer)->test(VehicleManagement::class)->call('editVehicle', $vehicle->id)->assertStatus(403);
        Livewire::actingAs($viewer)->test(VehicleManagement::class)->call('saveDraft')->assertStatus(403);
        Livewire::actingAs($viewer)->test(VehicleManagement::class)->call('saveVehicle')->assertStatus(403);
        Livewire::actingAs($viewer)->test(VehicleManagement::class)->set('selectedVehicleId', $vehicle->id)->call('toggleVehicleBlock')->assertStatus(403);

        Livewire::actingAs($viewer)->test(CompanyManagement::class)->call('createCompany')->assertStatus(403);
        Livewire::actingAs($viewer)->test(CompanyManagement::class)->call('editCompany', $company->id)->assertStatus(403);
        Livewire::actingAs($viewer)->test(CompanyManagement::class)->call('saveDraft')->assertStatus(403);
        Livewire::actingAs($viewer)->test(CompanyManagement::class)->call('saveCompany')->assertStatus(403);
        Livewire::actingAs($viewer)->test(CompanyManagement::class)->set('selectedCompanyId', $company->id)->call('toggleCompanyStatus')->assertStatus(403);

        $this->assertSame('ativo', $property->fresh()->status);
        $this->assertSame('ativo', $vehicle->fresh()->status);
        $this->assertSame('ativo', $company->fresh()->status);
    }

    public function test_cash_register_does_not_accept_a_foreign_or_closed_turn_id(): void
    {
        $operator = $this->operator('caixa.proprio.gerenciar');
        $anotherOperator = $this->operator('caixa.proprio.gerenciar');
        $foreign = CaixaTurno::factory()->create(['status' => 'aberto', 'operator_id' => $anotherOperator->id]);
        $closed = CaixaTurno::factory()->create(['status' => 'fechado', 'operator_id' => $operator->id]);

        foreach ([$foreign->id, $closed->id] as $turnId) {
            Livewire::actingAs($operator)->test(CashRegister::class)
                ->set('currentTurnoId', $turnId)
                ->set('movementType', 'entrada')
                ->set('movementAmount', '100,00')
                ->set('movementMethod', 'dinheiro')
                ->set('movementDescription', 'Lançamento indevido')
                ->call('registerMovement')
                ->assertSet('feedback.variant', 'danger');

            Livewire::actingAs($operator)->test(CashRegister::class)
                ->set('currentTurnoId', $turnId)
                ->set('informedAmount', '100,00')
                ->call('closeRegister')
                ->assertSet('status', 'fechado');
        }

        $this->assertDatabaseCount('caixa_movimentacoes', 0);
        $this->assertSame('aberto', $foreign->fresh()->status);
        $this->assertSame('fechado', $closed->fresh()->status);
    }

    public function test_cash_register_requires_write_permission_on_every_action(): void
    {
        $viewer = $this->operator('caixa.consolidado.consultar');

        Livewire::actingAs($viewer)->test(CashRegister::class)->call('openRegister')->assertStatus(403);
        Livewire::actingAs($viewer)->test(CashRegister::class)->call('registerMovement')->assertStatus(403);
        Livewire::actingAs($viewer)->test(CashRegister::class)->call('closeRegister')->assertStatus(403);
    }

    public function test_login_clears_password_after_bad_credentials(): void
    {
        Livewire::test(Login::class)
            ->set('identification', 'usuario-inexistente')
            ->set('password', 'senha-de-teste')
            ->call('login')
            ->assertHasErrors('credentials')
            ->assertSet('password', '');
    }

    public function test_password_reset_clears_sensitive_fields_on_invalid_token(): void
    {
        $user = User::factory()->create();

        Livewire::test(ResetPassword::class, ['token' => 'invalido'])
            ->set('email', $user->email)
            ->set('password', 'senha-nova-de-teste')
            ->set('password_confirmation', 'senha-nova-de-teste')
            ->call('resetPassword')
            ->assertHasErrors('token')
            ->assertSet('password', '')
            ->assertSet('password_confirmation', '');
    }
}
