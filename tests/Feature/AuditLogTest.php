<?php

namespace Tests\Feature;

use App\Livewire\AuditLog;
use App\Models\AuditoriaEvento;
use App\Models\Implantacao;
use App\Models\Perfil;
use App\Models\Permissao;
use App\Models\Pessoa;
use App\Models\User;
use App\Models\UsuarioPerfil;
use App\Services\AuditService;
use App\Support\ImplantacaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
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

    public function test_operational_model_changes_are_recorded_with_actor_and_context(): void
    {
        $operator = User::factory()->create();
        $this->actingAs($operator);

        $person = Pessoa::factory()->create(['nome' => 'Marina Lopes']);
        $person->update(['nome' => 'Marina Lopes Souza']);

        $event = AuditoriaEvento::query()
            ->where('entity_type', 'pessoas')
            ->where('entity_id', $person->id)
            ->where('action', 'alterou')
            ->with(['changes', 'context'])
            ->sole();

        $this->assertSame($operator->id, $event->actor_id);
        $this->assertSame('Marina Lopes', $event->changes->firstWhere('field_name', 'nome')->old_value);
        $this->assertSame('Marina Lopes Souza', $event->changes->firstWhere('field_name', 'nome')->new_value);
        $this->assertNotNull($event->context);
        $this->assertTrue(Str::isUuid($event->correlation_id));
    }

    public function test_sensitive_values_are_never_written_to_the_audit_trail(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $plainPassword = 'Senha-super-secreta-123';
        $user->update(['password' => Hash::make($plainPassword)]);

        $change = AuditoriaEvento::query()
            ->where('entity_type', 'users')
            ->where('entity_id', (string) $user->id)
            ->where('action', 'alterou')
            ->latest('occurred_at')
            ->firstOrFail()
            ->changes()
            ->where('field_name', 'password')
            ->sole();

        $this->assertTrue($change->is_masked);
        $this->assertSame('[DADO PROTEGIDO]', $change->old_value);
        $this->assertSame('[DADO PROTEGIDO]', $change->new_value);
        $this->assertDatabaseMissing('auditoria_alteracoes', ['new_value' => $plainPassword]);
    }

    public function test_audit_records_cannot_be_changed_or_deleted_through_the_model(): void
    {
        $event = app(AuditService::class)->record('consultou', 'teste', 'recurso', '123');

        try {
            $event->update(['result' => 'falha']);
            $this->fail('A atualização deveria ser bloqueada.');
        } catch (LogicException $exception) {
            $this->assertSame('Registros de auditoria são imutáveis.', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $event->delete();
    }

    public function test_audit_queries_are_isolated_by_implantation(): void
    {
        app(AuditService::class)->record('criou', 'teste', 'recurso', 'SANTA-RITA');

        $other = Implantacao::factory()->create();
        ImplantacaoContext::setCurrentForTesting($other);
        app(AuditService::class)->record('criou', 'teste', 'recurso', 'OUTRA');

        $this->assertSame(['OUTRA'], AuditoriaEvento::query()->pluck('entity_id')->all());
    }

    public function test_route_requires_the_specific_audit_permission(): void
    {
        $this->get('/auditoria')->assertRedirect(route('login'));

        $withoutPermission = User::factory()->create();
        $this->actingAs($withoutPermission)->get('/auditoria')->assertRedirect(route('dashboard'));

        $auditor = $this->userWithPermissions('auditoria.consultar');
        $this->actingAs($auditor)->get('/auditoria')->assertOk()->assertSee('Logs e auditoria');
    }

    public function test_authorized_user_can_filter_open_and_export_audit_records(): void
    {
        $auditor = $this->userWithPermissions('auditoria.consultar', 'auditoria.exportar');
        $this->actingAs($auditor);

        $cash = app(AuditService::class)->record('abriu_caixa', 'caixa', 'caixa_turnos', 'CX-01');
        app(AuditService::class)->record('consultou', 'pessoas', 'pessoas', 'PS-01');

        Livewire::actingAs($auditor)
            ->test(AuditLog::class)
            ->set('moduleFilter', 'caixa')
            ->assertSee('CX-01')
            ->assertDontSee('PS-01')
            ->call('openEvent', $cash->id)
            ->assertSee('Detalhes da auditoria')
            ->call('exportCsv')
            ->assertFileDownloaded('auditoria-'.now()->subDays(30)->toDateString().'-a-'.now()->toDateString().'.csv');

        $this->assertDatabaseHas('auditoria_eventos', [
            'action' => 'consultou_detalhe',
            'entity_id' => $cash->id,
        ]);
        $this->assertDatabaseHas('auditoria_eventos', [
            'action' => 'exportou_csv',
            'module' => 'auditoria',
        ]);
    }

    private function userWithPermissions(string ...$keys): User
    {
        $user = User::factory()->create();
        $profile = Perfil::factory()->create();

        foreach ($keys as $key) {
            $permission = Permissao::factory()->create(['chave' => $key]);
            $profile->permissoes()->attach($permission->id, [
                'id' => (string) Str::uuid7(),
                'implantacao_id' => ImplantacaoContext::current()->id,
            ]);
        }

        UsuarioPerfil::factory()->for($user)->for($profile)->create();

        return $user;
    }
}
