<?php

namespace Tests\Feature;

use App\Exceptions\InvalidTemporalRangeException;
use App\Livewire\AccessHistory;
use App\Livewire\AccessValidation;
use App\Livewire\CashRegister;
use App\Livewire\CompanyManagement;
use App\Livewire\Dashboard;
use App\Livewire\Login;
use App\Livewire\PackageManagement;
use App\Livewire\PersonRegistration;
use App\Livewire\PreRegistrationQueue;
use App\Livewire\PropertyManagement;
use App\Livewire\PublicPreRegistration;
use App\Livewire\VehicleManagement;
use App\Models\EnderecoImovel;
use App\Models\Imovel;
use App\Models\ImovelResponsabilidade;
use App\Models\Implantacao;
use App\Models\Perfil;
use App\Models\Permissao;
use App\Models\Pessoa;
use App\Models\PessoaDocumento;
use App\Models\PreRegistration;
use App\Models\PreRegistrationEdit;
use App\Models\User;
use App\Models\UsuarioImplantacao;
use App\Models\UsuarioPerfil;
use App\Models\Veiculo;
use App\Models\VeiculoVinculo;
use App\Models\Vinculo;
use App\Support\ImplantacaoContext;
use Database\Seeders\UsuarioDemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ExampleTest extends TestCase
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

    private function portariaOperator(bool $canEdit = true): User
    {
        $user = User::factory()->create([
            'username' => 'portaria',
            'password' => 'sdv2026',
        ]);

        if ($canEdit) {
            $permissao = Permissao::query()->firstOrCreate(
                ['chave' => 'pre-registro.editar'],
                ['modulo' => 'pre-cadastro', 'descricao' => 'Corrigir dados de um pré-cadastro antes da aprovação']
            );
            $perfil = Perfil::factory()->create();
            $perfil->permissoes()->attach($permissao->id, ['id' => (string) Str::uuid7(), 'implantacao_id' => Implantacao::current()->id]);

            UsuarioPerfil::factory()->for($user)->for($perfil)->create();
        }

        return $user;
    }

    private function operatorWithPermissions(string ...$chaves): User
    {
        $user = User::factory()->create();
        $perfil = Perfil::factory()->create();

        foreach ($chaves as $chave) {
            $permissao = Permissao::query()->firstOrCreate(['chave' => $chave], ['modulo' => 'teste', 'descricao' => $chave]);
            $perfil->permissoes()->attach($permissao->id, ['id' => (string) Str::uuid7(), 'implantacao_id' => Implantacao::current()->id]);
        }

        UsuarioPerfil::factory()->for($user)->for($perfil)->create();

        return $user;
    }

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
        $this->portariaOperator();

        Livewire::test(Login::class)
            ->call('useDemoAccount')
            ->assertSet('identification', 'portaria')
            ->assertSet('password', 'sdv2026')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_logging_out_invalidates_the_session_and_redirects_to_login(): void
    {
        $user = $this->portariaOperator();
        $this->actingAs($user);

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_dashboard_shows_the_real_logged_in_operator_name(): void
    {
        $this->withoutVite();
        $user = $this->portariaOperator();
        $user->update(['name' => 'Operador de Teste']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()->assertSee('Operador de Teste');
    }

    public function test_the_dashboard_renders_the_approved_visual_structure(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create());

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

    public function test_the_access_validation_renders_the_complete_p06_journey(): void
    {
        $this->withoutVite();
        $this->actingAs($this->operatorWithPermissions('validacao.registrar'));

        $response = $this->get('/validacao');

        $response
            ->assertOk()
            ->assertSee('Validação de entrada')
            ->assertSee('Identificação da pessoa')
            ->assertSee('Marcos Vinicius da Silva')
            ->assertSee('Veículo e leitura da placa')
            ->assertSee('ABC1D23')
            ->assertSee('Contribuição / taxa de acesso')
            ->assertSee('Observações')
            ->assertSee('Negar entrada')
            ->assertSee('Salvar sem liberar')
            ->assertSee('Validar e liberar')
            ->assertSee('nenhuma das ações desta página envia comandos para equipamentos físicos');
    }

    public function test_the_access_validation_decisions_produce_safe_demo_feedback(): void
    {
        Livewire::test(AccessValidation::class)
            ->assertSet('contribution', 'yes')
            ->set('notes', 'Visitante conferido pela portaria.')
            ->call('savePending')
            ->assertSet('feedback.variant', 'warning')
            ->assertSet('protocol', 'SRA-20260810-004184')
            ->call('release')
            ->assertSet('feedback.variant', 'success')
            ->assertSee('Nenhum portão ou equipamento real foi acionado.')
            ->set('denialReason', 'documento_invalido')
            ->set('denialDetails', 'Documento apresentado não confere.')
            ->call('deny')
            ->assertSet('feedback.variant', 'danger')
            ->assertSet('protocol', 'SRA-20260810-004183')
            ->assertSee('Nenhum comando de abertura foi enviado.');
    }

    public function test_the_quick_registration_preserves_the_validation_in_progress(): void
    {
        Livewire::test(AccessValidation::class)
            ->set('contribution', 'no')
            ->set('notes', 'Atendimento iniciado antes do cadastro rápido.')
            ->call('openQuickRegistration')
            ->assertSet('quickRegistrationOpen', true)
            ->set('quickName', 'Carlos Eduardo Lima')
            ->set('quickDocument', '987.654.321-00')
            ->set('quickPhone', '(12) 99999-1234')
            ->set('quickAccessType', 'visitante')
            ->set('quickResponsible', 'Marcos Vinicius da Silva')
            ->set('quickPropertyCode', 'SRA-A-102')
            ->set('quickNotes', 'Documento será complementado depois.')
            ->call('saveQuickRegistration')
            ->assertHasNoErrors()
            ->assertSet('quickRegistrationOpen', false)
            ->assertSet('quickPersonRegistered', true)
            ->assertSet('currentPerson.name', 'Carlos Eduardo Lima')
            ->assertSet('currentPerson.status', 'Cadastro mínimo')
            ->assertSet('contribution', 'no')
            ->assertSet('notes', 'Atendimento iniciado antes do cadastro rápido.')
            ->assertSet('feedback.variant', 'warning')
            ->assertSee('O cadastro ainda não autoriza a entrada');
    }

    public function test_the_quick_registration_prevents_duplicate_people(): void
    {
        Livewire::test(AccessValidation::class)
            ->call('openQuickRegistration')
            ->set('quickName', 'Pessoa já cadastrada')
            ->set('quickDocument', '111.111.111-11')
            ->set('quickPhone', '(12) 98888-0000')
            ->set('quickResponsible', 'Administração')
            ->set('quickPropertyCode', 'SRA-A-102')
            ->call('saveQuickRegistration')
            ->assertSet('quickDuplicateFound', true)
            ->assertSet('quickPersonRegistered', false)
            ->assertSet('quickRegistrationOpen', true)
            ->assertHasErrors(['quickDocument'])
            ->assertSee('Não criaremos outro registro com este documento.');
    }

    public function test_the_quick_registration_can_be_cancelled_without_losing_the_validation(): void
    {
        Livewire::test(AccessValidation::class)
            ->set('paymentMethod', 'pix')
            ->set('notes', 'Observação que deve continuar no atendimento.')
            ->call('openQuickRegistration')
            ->set('quickName', 'Cadastro cancelado')
            ->call('cancelQuickRegistration')
            ->assertSet('quickRegistrationOpen', false)
            ->assertSet('quickName', '')
            ->assertSet('paymentMethod', 'pix')
            ->assertSet('notes', 'Observação que deve continuar no atendimento.');
    }

    public function test_a_quick_registration_does_not_release_an_entry(): void
    {
        Livewire::test(AccessValidation::class)
            ->call('openQuickRegistration')
            ->set('quickName', 'Ana Paula Ribeiro')
            ->set('quickDocument', '456.789.123-00')
            ->set('quickPhone', '(12) 97777-1122')
            ->set('quickAccessType', 'visitante')
            ->set('quickResponsible', 'Marcos Vinicius da Silva')
            ->set('quickPropertyCode', 'SRA-A-102')
            ->call('saveQuickRegistration')
            ->call('release')
            ->assertSet('feedback.variant', 'warning')
            ->assertSet('feedback.title', 'Liberação não realizada')
            ->assertSet('protocol', 'SRA-20260810-004186')
            ->assertSee('O cadastro rápido não possui autorização válida.');
    }

    public function test_the_public_pre_registration_renders_the_secure_invitation(): void
    {
        $this->withoutVite();

        $response = $this->get('/pre-cadastro/convite-demonstracao');

        $response
            ->assertOk()
            ->assertSee('Bem-vindo ao Santa Rita')
            ->assertSee('Convite válido')
            ->assertSee('Bloco B · Apto 304')
            ->assertSee('Iniciar pré-cadastro')
            ->assertSee('O envio não garante entrada');
    }

    public function test_the_public_pre_registration_preserves_the_six_step_journey(): void
    {
        Livewire::test(PublicPreRegistration::class)
            ->call('start')
            ->assertSet('step', 1)
            ->set('name', 'Camila Andrade')
            ->set('cpf', '987.654.321-00')
            ->set('birthDate', '1992-05-15')
            ->set('phone', '(12) 99999-9999')
            ->set('email', 'camila@example.com')
            ->call('nextStep')
            ->assertSet('step', 2)
            ->set('zipCode', '12000-000')
            ->set('address', 'Rua das Flores')
            ->set('addressNumber', '120')
            ->set('district', 'Centro')
            ->set('city', 'Taubaté')
            ->set('state', 'SP')
            ->call('nextStep')
            ->assertSet('step', 3)
            ->call('markDocumentReady')
            ->call('nextStep')
            ->assertSet('step', 4)
            ->call('markSelfieReady')
            ->call('nextStep')
            ->assertSet('step', 5)
            ->call('nextStep')
            ->assertSet('step', 6)
            ->set('privacyAccepted', true)
            ->call('submit')
            ->assertSet('submitted', true)
            ->assertSee('O protocolo não é uma autorização');

        $this->assertDatabaseHas('pre_registrations', [
            'name' => 'Camila Andrade',
            'document' => '987.654.321-00',
            'access_type' => 'turista',
            'destination_label' => 'Praia do Santa Rita',
            'destination_property' => null,
            'responsible_name' => null,
        ]);
    }

    public function test_the_public_pre_registration_persists_a_visitor_with_vehicle_and_destination(): void
    {
        Livewire::test(PublicPreRegistration::class)
            ->call('start')
            ->set('accessType', 'visitante')
            ->set('name', 'Felipe Martins')
            ->set('cpf', '444.004.117-61')
            ->set('birthDate', '1988-02-20')
            ->set('phone', '(12) 98888-7777')
            ->set('email', 'felipe@example.com')
            ->call('nextStep')
            ->assertSet('step', 2)
            ->set('zipCode', '12010-000')
            ->set('address', 'Rua das Acácias')
            ->set('addressNumber', '42')
            ->set('district', 'Jardim das Flores')
            ->set('city', 'Taubaté')
            ->set('state', 'SP')
            ->set('destinationProperty', 'Bloco B — Apto 304')
            ->call('nextStep')
            ->assertSet('step', 3)
            ->call('markDocumentReady')
            ->call('nextStep')
            ->call('markSelfieReady')
            ->call('nextStep')
            ->assertSet('step', 5)
            ->set('hasVehicle', true)
            ->set('plate', 'ABC1D23')
            ->set('vehicleModel', 'Corolla')
            ->set('vehicleColor', 'Prata')
            ->call('nextStep')
            ->assertSet('step', 6)
            ->set('privacyAccepted', true)
            ->call('submit')
            ->assertSet('submitted', true);

        $this->assertDatabaseHas('pre_registrations', [
            'name' => 'Felipe Martins',
            'access_type' => 'visitante',
            'address_informed' => 'Rua das Acácias, 42 · Jardim das Flores · Taubaté/SP',
            'destination_property' => 'Bloco B — Apto 304',
            'destination_label' => 'Bloco B — Apto 304',
            'responsible_name' => 'Mariana Souza',
            'vehicle_plate' => 'ABC1D23',
            'vehicle_model' => 'Corolla',
            'vehicle_color' => 'Prata',
        ]);
    }

    public function test_the_pre_registration_queue_renders_filters_and_analysis(): void
    {
        $this->withoutVite();
        $this->actingAs($this->operatorWithPermissions('pre-registro.analisar'));

        PreRegistration::factory()->create([
            'name' => 'Camila Andrade',
            'protocol' => 'PRE-SRA-X7K9M2',
            'status' => 'aguardando',
        ]);

        $response = $this->get('/pre-cadastros');

        $response
            ->assertOk()
            ->assertSee('Solicitações')
            ->assertSee('Aguardando análise')
            ->assertSee('Camila Andrade')
            ->assertSee('PRE-SRA-X7K9M2')
            ->assertSee('Aprovar pré-cadastro')
            ->assertSee('Aprovação não garante entrada');
    }

    public function test_the_pre_registration_queue_records_each_demo_decision(): void
    {
        $first = PreRegistration::factory()->create(['status' => 'aguardando']);
        $second = PreRegistration::factory()->create(['status' => 'aguardando']);

        Livewire::test(PreRegistrationQueue::class)
            ->call('approve', $first->id)
            ->assertSet('feedback.variant', 'success')
            ->call('requestCorrection', $second->id)
            ->assertSet('feedback.variant', 'warning')
            ->call('reject', $second->id)
            ->assertSet('feedback.variant', 'danger')
            ->assertSee('A observação interna não será enviada ao solicitante.');

        $this->assertSame('aprovado', $first->fresh()->status);
        $this->assertSame('rejeitado', $second->fresh()->status);
        $this->assertSame(3, $second->fresh()->version, 'requestCorrection e reject cada um deve incrementar a versão.');
    }

    public function test_the_pre_registration_detail_shows_every_filled_field(): void
    {
        $record = PreRegistration::factory()->visitor()->create([
            'name' => 'Camila Andrade',
            'document' => '***.***.331-**',
            'phone' => '(12) 99876-4321',
            'email' => 'camila.andrade@example.com',
            'address_informed' => 'Rua das Palmeiras, 125 · Centro · Taubaté/SP',
            'vehicle_plate' => 'ABC1D23',
            'document_status' => 'Documento enviado e legível',
            'selfie_status' => 'Selfie enviada e adequada',
            'protocol' => 'PRE-SRA-X7K9M2',
        ]);

        Livewire::test(PreRegistrationQueue::class)
            ->assertSee('Camila Andrade')
            ->assertSee('***.***.331-**')
            ->assertSee('(12) 99876-4321')
            ->assertSee('camila.andrade@example.com')
            ->assertSee('Rua das Palmeiras, 125 · Centro · Taubaté/SP')
            ->assertSee('Visitante')
            ->assertSee($record->destination_property)
            ->assertSee($record->responsible_name)
            ->assertSee('ABC1D23')
            ->assertSee('Documento enviado e legível')
            ->assertSee('Selfie enviada e adequada')
            ->assertSee('PRE-SRA-X7K9M2')
            ->assertSee('Dados preenchidos');
    }

    public function test_the_gate_operator_can_edit_submitted_data_with_an_audit_reason_before_approval(): void
    {
        $operator = $this->portariaOperator();
        $record = PreRegistration::factory()->visitor()->create(['phone' => '(12) 99876-4321']);

        $this->actingAs($operator);

        Livewire::test(PreRegistrationQueue::class)
            ->call('beginEdit', $record->id)
            ->assertSet('editingId', $record->id)
            ->assertSet('editPhone', '(12) 99876-4321')
            ->set('editPhone', '(12) 99999-0000')
            ->set('editReason', 'Telefone confirmado com a visitante.')
            ->call('saveEdit', $record->id)
            ->assertHasNoErrors()
            ->assertSet('editingId', null)
            ->assertSee('Correção salva com auditoria')
            ->assertSee('Telefone confirmado com a visitante.');

        $record->refresh();
        $this->assertSame('(12) 99999-0000', $record->phone);
        $this->assertSame(2, $record->version);
    }

    public function test_editing_preserves_the_original_value_in_the_audit_trail(): void
    {
        $operator = $this->portariaOperator();
        $record = PreRegistration::factory()->visitor()->create(['name' => 'Camila Andrade']);

        $this->actingAs($operator);

        Livewire::test(PreRegistrationQueue::class)
            ->call('beginEdit', $record->id)
            ->set('editName', 'Camila Andrade Ferreira')
            ->set('editReason', 'Nome completo corrigido conforme documento apresentado.')
            ->call('saveEdit', $record->id)
            ->assertHasNoErrors();

        $edit = PreRegistrationEdit::query()->where('pre_registration_id', $record->id)->where('field', 'Nome completo')->sole();

        $this->assertSame('Camila Andrade', $edit->old_value);
        $this->assertSame('Camila Andrade Ferreira', $edit->new_value);
        $this->assertSame('aguardando', $record->fresh()->status, 'a correção não pode aprovar nem alterar a situação sozinha');
    }

    public function test_editing_records_a_persistent_audit_entry_with_operator_and_timestamp(): void
    {
        $operator = $this->portariaOperator();
        $record = PreRegistration::factory()->visitor()->create();

        $this->actingAs($operator);

        Livewire::test(PreRegistrationQueue::class)
            ->call('beginEdit', $record->id)
            ->set('editEmail', 'novo-email@example.com')
            ->set('editReason', 'E-mail corrigido a pedido da visitante.')
            ->call('saveEdit', $record->id)
            ->assertHasNoErrors();

        $edit = PreRegistrationEdit::query()->where('pre_registration_id', $record->id)->where('field', 'E-mail')->sole();

        $this->assertSame($operator->id, $edit->operator_id);
        $this->assertSame($operator->name, $edit->operator_name);
        $this->assertSame('E-mail corrigido a pedido da visitante.', $edit->reason);
        $this->assertSame('sucesso', $edit->result);
        $this->assertNotNull($edit->occurred_at);
    }

    public function test_the_gate_operator_cannot_approve_while_an_edit_is_open(): void
    {
        $operator = $this->portariaOperator();
        $record = PreRegistration::factory()->visitor()->create();

        $this->actingAs($operator);

        Livewire::test(PreRegistrationQueue::class)
            ->call('beginEdit', $record->id)
            ->call('approve', $record->id)
            ->assertHasErrors('editReason');

        $this->assertSame('aguardando', $record->fresh()->status);
    }

    public function test_editing_is_denied_without_the_specific_permission(): void
    {
        $operatorWithoutPermission = $this->portariaOperator(canEdit: false);
        $record = PreRegistration::factory()->visitor()->create();

        $this->actingAs($operatorWithoutPermission);

        Livewire::test(PreRegistrationQueue::class)
            ->call('beginEdit', $record->id)
            ->assertSet('editingId', null);

        $this->assertFalse($operatorWithoutPermission->fresh()->hasPermission('pre-registro.editar'));
    }

    public function test_editing_is_denied_for_a_guest_operator(): void
    {
        $record = PreRegistration::factory()->visitor()->create();

        Livewire::test(PreRegistrationQueue::class)
            ->call('beginEdit', $record->id)
            ->assertSet('editingId', null);
    }

    public function test_editing_is_blocked_once_the_pre_registration_left_the_analysis_state(): void
    {
        $operator = $this->portariaOperator();
        $record = PreRegistration::factory()->visitor()->withStatus('aprovado')->create();

        $this->actingAs($operator);

        Livewire::test(PreRegistrationQueue::class)
            ->call('beginEdit', $record->id)
            ->assertSet('editingId', null)
            ->assertHasErrors('state');
    }

    public function test_saving_an_edit_fails_on_a_version_conflict_instead_of_overwriting(): void
    {
        $operator = $this->portariaOperator();
        $record = PreRegistration::factory()->visitor()->create(['phone' => '(12) 90000-0000']);

        $this->actingAs($operator);

        $component = Livewire::test(PreRegistrationQueue::class)
            ->call('beginEdit', $record->id)
            ->assertSet('editVersion', 1);

        // Outro operador salva uma correção no mesmo pré-cadastro entre a abertura e o salvamento desta edição.
        $record->update(['version' => 2]);

        $component
            ->set('editPhone', '(12) 91111-1111')
            ->set('editReason', 'Telefone corrigido durante o atendimento.')
            ->call('saveEdit', $record->id)
            ->assertHasErrors('editReason')
            ->assertSet('editingId', null);

        $this->assertSame('(12) 90000-0000', $record->fresh()->phone, 'a edição desatualizada não pode sobrescrever a versão mais recente');
        $this->assertSame(2, $record->fresh()->version);
    }

    public function test_a_tourist_pre_registration_does_not_require_a_property_or_a_responsible(): void
    {
        $record = PreRegistration::factory()->tourist()->create();

        $this->assertFalse($record->requiresProperty());
        $this->assertNull($record->destination_property);
        $this->assertNull($record->responsible_name);
        $this->assertSame('Praia do Santa Rita', $record->destination_label);

        Livewire::test(PreRegistrationQueue::class)
            ->assertSee('Praia do Santa Rita')
            ->assertSee('Não exige responsável de imóvel');
    }

    public function test_a_visitor_pre_registration_requires_a_property_and_a_responsible_when_edited(): void
    {
        $operator = $this->portariaOperator();
        $record = PreRegistration::factory()->visitor()->create();

        $this->assertTrue($record->requiresProperty());

        $this->actingAs($operator);

        Livewire::test(PreRegistrationQueue::class)
            ->call('beginEdit', $record->id)
            ->set('editDestinationProperty', '')
            ->set('editReason', 'Ajuste de imóvel de destino.')
            ->call('saveEdit', $record->id)
            ->assertHasErrors('editDestinationProperty');

        $this->assertSame($record->destination_property, $record->fresh()->destination_property, 'sem imóvel válido a alteração não deve ser persistida');
    }

    public function test_reading_imoveis_is_isolated_between_implantacoes(): void
    {
        $implantacaoA = Implantacao::current();

        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        $imovelB = Imovel::factory()->create(['codigo' => 'SRA-B-999']);

        ImplantacaoContext::setCurrentForTesting($implantacaoA);
        $imovelA = Imovel::factory()->create(['codigo' => 'SRA-A-999']);

        $visiveis = Imovel::query()->pluck('id');

        $this->assertTrue($visiveis->contains($imovelA->id));
        $this->assertFalse($visiveis->contains($imovelB->id), 'um imóvel de outra implantação não pode aparecer na listagem');
        $this->assertNull(Imovel::query()->find($imovelB->id), 'buscar por id conhecido de outra implantação deve falhar como não encontrado');
    }

    public function test_creating_an_imovel_always_uses_the_server_assigned_implantacao(): void
    {
        $implantacaoAtual = Implantacao::current();
        $outraImplantacao = Implantacao::factory()->create();

        $imovel = Imovel::factory()->create(['implantacao_id' => $outraImplantacao->id]);

        $this->assertSame($implantacaoAtual->id, $imovel->fresh()->implantacao_id, 'implantacao_id enviado pelo chamador não pode sobrescrever o contexto do servidor');
    }

    public function test_imovel_version_uses_optimistic_concurrency(): void
    {
        $imovel = Imovel::factory()->create(['versao' => 1]);

        $atualizadoPrimeiro = Imovel::query()->where('id', $imovel->id)->where('versao', 1)->update(['status' => 'inativo', 'versao' => 2]);
        $this->assertSame(1, $atualizadoPrimeiro);

        $atualizadoComVersaoDesatualizada = Imovel::query()->where('id', $imovel->id)->where('versao', 1)->update(['status' => 'ativo', 'versao' => 3]);
        $this->assertSame(0, $atualizadoComVersaoDesatualizada, 'uma atualização com versão desatualizada não pode sobrescrever a mais recente');

        $this->assertSame('inativo', $imovel->fresh()->status);
        $this->assertSame(2, $imovel->fresh()->versao);
    }

    public function test_pre_registrations_are_isolated_between_implantacoes_after_the_retrofit(): void
    {
        $implantacaoA = Implantacao::current();

        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        $registroB = PreRegistration::factory()->create();

        ImplantacaoContext::setCurrentForTesting($implantacaoA);
        $registroA = PreRegistration::factory()->create();

        $visiveis = PreRegistration::query()->pluck('id');

        $this->assertTrue($visiveis->contains($registroA->id));
        $this->assertFalse($visiveis->contains($registroB->id), 'pré-cadastros de outra implantação não podem vazar após o retrofit de implantacao_id');
    }

    public function test_reading_pessoas_is_isolated_between_implantacoes(): void
    {
        $implantacaoA = Implantacao::current();

        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        $pessoaB = Pessoa::factory()->create();

        ImplantacaoContext::setCurrentForTesting($implantacaoA);
        $pessoaA = Pessoa::factory()->create();

        $visiveis = Pessoa::query()->pluck('id');

        $this->assertTrue($visiveis->contains($pessoaA->id));
        $this->assertFalse($visiveis->contains($pessoaB->id), 'uma pessoa de outra implantação não pode aparecer na listagem');
        $this->assertNull(Pessoa::query()->find($pessoaB->id), 'buscar por id conhecido de outra implantação deve falhar como não encontrado');
    }

    public function test_pessoa_version_uses_optimistic_concurrency(): void
    {
        $pessoa = Pessoa::factory()->create(['versao' => 1]);

        $atualizadoPrimeiro = Pessoa::query()->where('id', $pessoa->id)->where('versao', 1)->update(['nome' => 'Nome Corrigido', 'versao' => 2]);
        $this->assertSame(1, $atualizadoPrimeiro);

        $atualizadoComVersaoDesatualizada = Pessoa::query()->where('id', $pessoa->id)->where('versao', 1)->update(['nome' => 'Sobrescrita Indevida', 'versao' => 3]);
        $this->assertSame(0, $atualizadoComVersaoDesatualizada, 'uma atualização com versão desatualizada não pode sobrescrever a mais recente');

        $this->assertSame('Nome Corrigido', $pessoa->fresh()->nome);
    }

    public function test_a_duplicate_document_is_rejected_within_the_same_implantacao_but_allowed_across_implantacoes(): void
    {
        $pessoa = Pessoa::factory()->create();
        PessoaDocumento::factory()->for($pessoa, 'pessoa')->create(['valor_normalizado' => '12345678900']);

        $outraPessoaMesmaImplantacao = Pessoa::factory()->create();

        try {
            PessoaDocumento::factory()->for($outraPessoaMesmaImplantacao, 'pessoa')->create(['valor_normalizado' => '12345678900']);
            $this->fail('um CPF já cadastrado na mesma implantação não pode ser duplicado em outra pessoa.');
        } catch (QueryException $exception) {
            $this->assertTrue(true);
        }

        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        $pessoaOutraImplantacao = Pessoa::factory()->create();

        $documentoRepetidoEmOutraImplantacao = PessoaDocumento::factory()->for($pessoaOutraImplantacao, 'pessoa')->create(['valor_normalizado' => '12345678900']);
        $this->assertSame('12345678900', $documentoRepetidoEmOutraImplantacao->valor_normalizado, 'o mesmo número de documento é permitido em implantações diferentes');
    }

    public function test_reading_vinculos_is_isolated_between_implantacoes(): void
    {
        $implantacaoA = Implantacao::current();

        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        $vinculoB = Vinculo::factory()->create();

        ImplantacaoContext::setCurrentForTesting($implantacaoA);
        $vinculoA = Vinculo::factory()->create();

        $visiveis = Vinculo::query()->pluck('id');

        $this->assertTrue($visiveis->contains($vinculoA->id));
        $this->assertFalse($visiveis->contains($vinculoB->id), 'um vínculo de outra implantação não pode aparecer na listagem');
    }

    public function test_vinculo_version_uses_optimistic_concurrency(): void
    {
        $vinculo = Vinculo::factory()->create(['versao' => 1]);

        $atualizadoPrimeiro = Vinculo::query()->where('id', $vinculo->id)->where('versao', 1)->update(['papel' => 'conjuge', 'versao' => 2]);
        $this->assertSame(1, $atualizadoPrimeiro);

        $atualizadoComVersaoDesatualizada = Vinculo::query()->where('id', $vinculo->id)->where('versao', 1)->update(['papel' => 'filho', 'versao' => 3]);
        $this->assertSame(0, $atualizadoComVersaoDesatualizada, 'uma atualização com versão desatualizada não pode sobrescrever a mais recente');

        $this->assertSame('conjuge', $vinculo->fresh()->papel);
    }

    public function test_a_vinculo_cannot_end_before_it_starts(): void
    {
        $vinculo = Vinculo::factory()->create();

        $this->expectException(InvalidTemporalRangeException::class);

        $vinculo->update(['ended_at' => $vinculo->started_at->copy()->subDay()]);
    }

    public function test_imovel_responsabilidade_links_the_main_responsible_to_the_property(): void
    {
        $pessoa = Pessoa::factory()->create(['nome' => 'Responsável de Teste']);
        $imovel = Imovel::factory()->create();
        $vinculo = Vinculo::factory()->for($pessoa, 'pessoa')->for($imovel, 'imovel')->create();

        ImovelResponsabilidade::factory()->for($imovel, 'imovel')->for($vinculo, 'vinculo')->create();

        $this->assertSame('Responsável de Teste', $imovel->responsavelPrincipal()?->nome);
    }

    public function test_reading_veiculos_is_isolated_between_implantacoes(): void
    {
        $implantacaoA = Implantacao::current();

        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        $veiculoB = Veiculo::factory()->create(['plate_normalized' => 'ABC1234']);

        ImplantacaoContext::setCurrentForTesting($implantacaoA);
        $veiculoA = Veiculo::factory()->create(['plate_normalized' => 'XYZ9876']);

        $visiveis = Veiculo::query()->pluck('id');

        $this->assertTrue($visiveis->contains($veiculoA->id));
        $this->assertFalse($visiveis->contains($veiculoB->id), 'um veículo de outra implantação não pode aparecer na listagem');
    }

    public function test_a_duplicate_plate_is_rejected_within_the_same_implantacao_but_allowed_across_implantacoes(): void
    {
        Veiculo::factory()->create(['plate_normalized' => 'ABC1D23']);

        try {
            Veiculo::factory()->create(['plate_normalized' => 'ABC1D23']);
            $this->fail('uma placa já cadastrada na mesma implantação não pode ser duplicada.');
        } catch (QueryException $exception) {
            $this->assertTrue(true);
        }

        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        $veiculoOutraImplantacao = Veiculo::factory()->create(['plate_normalized' => 'ABC1D23']);
        $this->assertSame('ABC1D23', $veiculoOutraImplantacao->plate_normalized, 'a mesma placa é permitida em implantações diferentes');
    }

    public function test_veiculo_vinculo_resolves_the_owner_of_the_vehicle(): void
    {
        $pessoa = Pessoa::factory()->create(['nome' => 'Dono do Veículo']);
        $veiculo = Veiculo::factory()->create();

        VeiculoVinculo::factory()->for($veiculo, 'veiculo')->for($pessoa, 'pessoa')->create();

        $this->assertSame('Dono do Veículo', $veiculo->proprietario()?->nome);
    }

    public function test_reading_usuario_implantacoes_is_isolated_between_implantacoes(): void
    {
        $implantacaoA = Implantacao::current();
        $user = User::factory()->create();

        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        UsuarioImplantacao::factory()->for($user)->create();

        ImplantacaoContext::setCurrentForTesting($implantacaoA);
        UsuarioImplantacao::factory()->for($user)->create();

        $this->assertSame(1, $user->implantacoes()->count(), 'só a associação da implantação atual deve ser visível');
    }

    public function test_a_perfil_grants_the_permission_it_was_given(): void
    {
        $user = User::factory()->create();
        $permissao = Permissao::factory()->create(['chave' => 'teste.permissao']);
        $perfil = Perfil::factory()->create();
        $perfil->permissoes()->attach($permissao->id, ['id' => (string) Str::uuid7(), 'implantacao_id' => Implantacao::current()->id]);

        UsuarioPerfil::factory()->for($user)->for($perfil)->create();

        $this->assertTrue($user->hasPermission('teste.permissao'));
    }

    public function test_a_user_without_a_perfil_does_not_have_the_permission(): void
    {
        $user = User::factory()->create();
        Permissao::factory()->create(['chave' => 'teste.permissao']);

        $this->assertFalse($user->hasPermission('teste.permissao'));
    }

    public function test_the_demo_seeder_grants_the_spec_permissions_to_each_perfil(): void
    {
        $this->seed(UsuarioDemoSeeder::class);

        $porteiroCaixa = Perfil::query()->where('nome', 'Porteiro/Caixa')->firstOrFail();
        $gestor = Perfil::query()->where('nome', 'Gestor')->firstOrFail();
        $auditor = Perfil::query()->where('nome', 'Auditor')->firstOrFail();
        $administrador = Perfil::query()->where('nome', 'Administrador')->firstOrFail();

        $this->assertTrue($porteiroCaixa->concede('validacao.registrar'));
        $this->assertTrue($porteiroCaixa->concede('veiculos.consultar'));
        $this->assertTrue($porteiroCaixa->concede('empresas.consultar'));
        $this->assertTrue($porteiroCaixa->concede('encomendas.registrar'));
        $this->assertFalse($porteiroCaixa->concede('usuarios.administrar'));
        $this->assertFalse($porteiroCaixa->concede('caixa.consolidado.consultar'));

        $this->assertTrue($gestor->concede('validacao.registrar'), 'gestor herda tudo do porteiro/caixa');
        $this->assertTrue($gestor->concede('caixa.consolidado.consultar'));
        $this->assertTrue($gestor->concede('autorizacoes.gerenciar'));
        $this->assertFalse($gestor->concede('usuarios.administrar'));
        $this->assertFalse($gestor->concede('perfis.administrar'));

        $this->assertTrue($auditor->concede('relatorios.consolidado.consultar'));
        $this->assertTrue($auditor->concede('imoveis.consultar'));
        $this->assertFalse($auditor->concede('validacao.registrar'), 'auditor só consulta, não registra validações');
        $this->assertFalse($auditor->concede('pre-registro.aprovar'));
        $this->assertFalse($auditor->concede('caixa.proprio.gerenciar'));

        $this->assertTrue($administrador->concede('usuarios.administrar'));
        $this->assertTrue($administrador->concede('perfis.administrar'));
        $this->assertTrue($administrador->concede('configuracoes.gerenciar'));
        $this->assertTrue($administrador->concede('validacao.registrar'), 'administrador tem acesso completo');
    }

    public function test_the_demo_seeder_assigns_each_demo_user_to_the_matching_perfil(): void
    {
        $this->seed(UsuarioDemoSeeder::class);

        $porteiro = User::query()->where('username', 'portaria')->firstOrFail();
        $leitura = User::query()->where('username', 'portaria.leitura')->firstOrFail();
        $gestor = User::query()->where('username', 'gestor')->firstOrFail();
        $administrador = User::query()->where('username', 'administrador')->firstOrFail();

        $this->assertTrue($porteiro->hasPermission('validacao.registrar'));
        $this->assertTrue($leitura->hasPermission('relatorios.consolidado.consultar'));
        $this->assertFalse($leitura->hasPermission('validacao.registrar'));
        $this->assertTrue($gestor->hasPermission('autorizacoes.gerenciar'));
        $this->assertTrue($administrador->hasPermission('usuarios.administrar'));
    }

    public function test_a_guest_is_redirected_to_the_login_when_visiting_a_protected_route(): void
    {
        $response = $this->get('/imoveis');

        $response->assertRedirect(route('login'));
    }

    public function test_an_authenticated_user_without_the_permission_is_redirected_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/imoveis');

        $response->assertRedirect(route('dashboard'));
        $this->assertSame('Sem permissão para acessar esta área.', session('erro'));
    }

    public function test_an_authenticated_user_with_the_permission_can_access_the_route(): void
    {
        $this->withoutVite();
        $this->actingAs($this->operatorWithPermissions('imoveis.consultar'));

        $response = $this->get('/imoveis');

        $response->assertOk();
    }

    public function test_the_dashboard_is_reachable_by_any_authenticated_user_regardless_of_perfil(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create());

        $response = $this->get('/dashboard');

        $response->assertOk();
    }

    public function test_the_public_pre_registration_route_does_not_require_authentication(): void
    {
        $this->withoutVite();

        $response = $this->get('/pre-cadastro/convite-demonstracao');

        $response->assertOk();
    }

    public function test_the_property_management_renders_the_p11_list(): void
    {
        $this->withoutVite();
        $this->actingAs($this->operatorWithPermissions('imoveis.consultar'));

        $imovel = Imovel::factory()->create(['codigo' => 'SRA-A-102']);
        EnderecoImovel::factory()->for($imovel, 'imovel')->create();

        $response = $this->get('/imoveis');

        $response
            ->assertOk()
            ->assertSee('Cadastro de imóveis')
            ->assertSee('SRA-A-102')
            ->assertSee('Responsável')
            ->assertSee('Ocupantes ativos')
            ->assertSee('Cadastrar imóvel')
            ->assertSee("openProperty('{$imovel->id}')", false);
    }

    public function test_the_property_detail_preserves_people_links_and_vehicles(): void
    {
        $imovel = Imovel::factory()->create();
        EnderecoImovel::factory()->for($imovel, 'imovel')->create();

        $pessoa = Pessoa::factory()->create(['nome' => 'Marcos Vinicius da Silva']);
        $vinculo = Vinculo::factory()->for($pessoa, 'pessoa')->for($imovel, 'imovel')->create();
        ImovelResponsabilidade::factory()->for($imovel, 'imovel')->for($vinculo, 'vinculo')->create();

        $veiculo = Veiculo::factory()->create(['plate_display' => 'ABC1D23']);
        VeiculoVinculo::factory()->for($veiculo, 'veiculo')->for($pessoa, 'pessoa')->for($imovel, 'imovel')->create();

        Livewire::test(PropertyManagement::class)
            ->call('openProperty', $imovel->id)
            ->assertSet('mode', 'detail')
            ->assertSet('selectedPropertyId', $imovel->id)
            ->assertSee('Pessoas e vínculos')
            ->assertSee('Responsável principal')
            ->assertSee('Marcos Vinicius da Silva')
            ->assertSee('ABC1D23')
            ->call('togglePropertyBlock')
            ->assertSet('feedback.variant', 'warning')
            ->assertSee('Os vínculos individuais continuam preservados');

        $this->assertDatabaseHas('imoveis', ['id' => $imovel->id, 'status' => 'bloqueado']);
    }

    public function test_the_property_form_creates_a_demo_property_without_implicit_links(): void
    {
        Livewire::test(PropertyManagement::class)
            ->call('createProperty')
            ->assertSet('mode', 'form')
            ->set('block', 'D')
            ->set('unit', '801')
            ->set('code', 'SRA-D-801')
            ->set('zipCode', '12000-000')
            ->set('street', 'Rua das Palmeiras')
            ->set('number', '80')
            ->set('district', 'Jardim das Flores')
            ->set('propertyStatus', 'ativo')
            ->call('saveProperty')
            ->assertHasNoErrors()
            ->assertSet('mode', 'detail')
            ->assertSee('Pessoas, vínculos e autorizações permanecem separados.');

        $this->assertDatabaseHas('imoveis', ['codigo' => 'SRA-D-801', 'unidade' => '801', 'status' => 'ativo']);
        $this->assertDatabaseHas('enderecos_imoveis', ['address' => 'Rua das Palmeiras', 'district' => 'Jardim das Flores']);
    }

    public function test_the_vehicle_management_renders_the_p12_list(): void
    {
        $this->withoutVite();
        $this->actingAs($this->operatorWithPermissions('veiculos.consultar'));

        $veiculo = Veiculo::factory()->create(['plate_display' => 'ABC1D23']);

        $response = $this->get('/veiculos');

        $response
            ->assertOk()
            ->assertSee('Cadastro de veículos')
            ->assertSee('ABC1D23')
            ->assertSee('Placas sincronizadas')
            ->assertSee('Cadastrar veículo')
            ->assertSee("openVehicle('{$veiculo->id}')", false);
    }

    public function test_the_vehicle_detail_preserves_links_when_blocked(): void
    {
        $pessoa = Pessoa::factory()->create(['nome' => 'Marcos Vinicius da Silva']);
        $imovel = Imovel::factory()->create(['codigo' => 'SRA-A-102']);
        $veiculo = Veiculo::factory()->create();
        VeiculoVinculo::factory()->for($veiculo, 'veiculo')->for($pessoa, 'pessoa')->for($imovel, 'imovel')->create();

        Livewire::test(VehicleManagement::class)
            ->call('openVehicle', $veiculo->id)
            ->assertSet('mode', 'detail')
            ->assertSet('selectedVehicleId', $veiculo->id)
            ->assertSee('Proprietário e vínculo')
            ->assertSee('Marcos Vinicius da Silva')
            ->assertSee('SRA-A-102')
            ->call('toggleVehicleBlock')
            ->assertSet('feedback.variant', 'warning')
            ->assertSee('O histórico e os vínculos foram preservados');

        $this->assertDatabaseHas('veiculos', ['id' => $veiculo->id, 'status' => 'bloqueado']);
    }

    public function test_the_vehicle_form_creates_a_demo_vehicle_without_releasing_access(): void
    {
        Pessoa::factory()->create(['nome' => 'Camila Andrade']);

        Livewire::test(VehicleManagement::class)
            ->call('createVehicle')
            ->set('plate', 'QRS-8T90')
            ->set('type', 'moto')
            ->set('brand', 'Yamaha')
            ->set('model', 'Fazer 250')
            ->set('color', 'Azul')
            ->set('year', '2025')
            ->set('renavam', '12345678901')
            ->set('owner', 'Camila Andrade')
            ->set('ownerDocument', '987.654.321-00')
            ->set('vehicleStatus', 'pendente')
            ->call('saveVehicle')
            ->assertHasNoErrors()
            ->assertSet('mode', 'detail')
            ->assertSee('Pessoa, imóvel, situação e autorização continuam independentes.');

        $this->assertDatabaseHas('veiculos', ['plate_normalized' => 'QRS8T90', 'status' => 'pendente']);
        $this->assertDatabaseHas('veiculo_vinculos', ['pessoa_id' => Pessoa::query()->where('nome', 'Camila Andrade')->value('id')]);
    }

    public function test_the_vehicle_form_rejects_a_duplicate_plate(): void
    {
        Pessoa::factory()->create(['nome' => 'Outra pessoa']);
        Veiculo::factory()->create(['plate_display' => 'ABC1D23', 'plate_normalized' => 'ABC1D23']);

        Livewire::test(VehicleManagement::class)
            ->call('createVehicle')
            ->set('plate', 'ABC-1D23')
            ->set('type', 'carro')
            ->set('brand', 'Toyota')
            ->set('model', 'Corolla')
            ->set('color', 'Prata')
            ->set('year', '2022')
            ->set('owner', 'Outra pessoa')
            ->call('saveVehicle')
            ->assertHasErrors(['plate'])
            ->assertSee('Esta placa já está cadastrada no sistema.');
    }

    public function test_the_vehicle_form_rejects_an_owner_that_does_not_exist(): void
    {
        Livewire::test(VehicleManagement::class)
            ->call('createVehicle')
            ->set('plate', 'QRS-8T90')
            ->set('type', 'moto')
            ->set('brand', 'Yamaha')
            ->set('model', 'Fazer 250')
            ->set('color', 'Azul')
            ->set('year', '2025')
            ->set('owner', 'Pessoa Inexistente')
            ->call('saveVehicle')
            ->assertHasErrors(['owner'])
            ->assertSee('Pessoa não encontrada.');
    }

    public function test_the_person_registration_renders_the_first_step(): void
    {
        $this->withoutVite();
        $this->actingAs($this->operatorWithPermissions('pessoas.gerenciar'));

        $response = $this->get('/pessoas/nova');

        $response
            ->assertOk()
            ->assertSee('Cadastro de pessoa')
            ->assertSee('Tipo de acesso')
            ->assertSee('Dados pessoais')
            ->assertSee('Nome completo')
            ->assertSee('Endereço e contato')
            ->assertSee('Informações de acesso')
            ->assertSee('Observações');
    }

    public function test_the_person_registration_blocks_advancing_without_required_fields(): void
    {
        Livewire::test(PersonRegistration::class)
            ->call('nextStep')
            ->assertHasErrors(['fullName', 'document', 'birthDate', 'phone'])
            ->assertSet('currentStep', 1);
    }

    public function test_the_person_registration_recalculates_fields_by_access_type(): void
    {
        Livewire::test(PersonRegistration::class)
            ->assertSet('nature', 'morador')
            ->assertSet('indefiniteTerm', true)
            ->set('accessType', 'provider')
            ->assertSet('nature', 'outro')
            ->assertSee('Empresa')
            ->set('accessType', 'tourist')
            ->assertSet('indefiniteTerm', false);
    }

    public function test_the_person_registration_detects_a_duplicate_document(): void
    {
        $pessoa = Pessoa::factory()->create();
        PessoaDocumento::factory()->for($pessoa, 'pessoa')->create([
            'tipo' => 'cpf',
            'valor_normalizado' => '11111111111',
        ]);

        Livewire::test(PersonRegistration::class)
            ->set('document', '111.111.111-11')
            ->call('checkDocument')
            ->assertSet('duplicateFound', true)
            ->assertSee('Já existe uma pessoa com este documento.');
    }

    public function test_the_person_registration_completes_the_five_step_journey(): void
    {
        $imovel = Imovel::factory()->create(['codigo' => 'SRA-A-102']);

        Livewire::test(PersonRegistration::class)
            ->set('fullName', 'Marcos Andrade Ferreira')
            ->set('document', '222.333.444-55')
            ->set('birthDate', '1990-05-14')
            ->set('phone', '(24) 99988-7766')
            ->call('nextStep')
            ->assertSet('currentStep', 2)
            ->call('nextStep')
            ->assertSet('currentStep', 3)
            ->set('property', 'SRA-A-102')
            ->call('nextStep')
            ->assertSet('currentStep', 4)
            ->set('startDate', '2026-08-10')
            ->call('nextStep')
            ->assertSet('currentStep', 5)
            ->call('activate')
            ->assertSet('feedback.variant', 'success')
            ->assertSee('O cadastro e o vínculo com o imóvel foram registrados.')
            ->assertSet('protocol', fn (?string $protocol) => $protocol !== null && str_starts_with($protocol, 'SRP-'));

        $this->assertDatabaseHas('pessoas', ['nome' => 'Marcos Andrade Ferreira']);
        $this->assertDatabaseHas('pessoa_documentos', ['valor_normalizado' => '22233344455']);
        $this->assertDatabaseHas('vinculos', ['imovel_id' => $imovel->id, 'tipo' => 'morador', 'papel' => 'titular']);
    }

    public function test_the_company_management_renders_the_p13_list(): void
    {
        $this->withoutVite();
        $this->actingAs($this->operatorWithPermissions('empresas.consultar'));

        $response = $this->get('/empresas');

        $response
            ->assertOk()
            ->assertSee('Empresas e prestadores')
            ->assertSee('ValeManutenção')
            ->assertSee('12.345.678/0001-90')
            ->assertSee('Prestadores vinculados')
            ->assertSee('Cadastrar empresa');
    }

    public function test_the_company_detail_preserves_providers_documents_and_services(): void
    {
        Livewire::test(CompanyManagement::class)
            ->call('openCompany', 1)
            ->assertSet('mode', 'detail')
            ->assertSet('selectedCompanyId', 1)
            ->assertSee('Prestadores vinculados')
            ->assertSee('Sérgio Aparecido Luz')
            ->assertSee('Manutenção elétrica')
            ->call('toggleCompanyStatus')
            ->assertSet('companies.0.status', 'inativo')
            ->assertSet('feedback.variant', 'warning')
            ->assertSee('impede novas autorizações');
    }

    public function test_the_company_form_creates_a_demo_company_without_implicit_providers(): void
    {
        Livewire::test(CompanyManagement::class)
            ->call('createCompany')
            ->assertSet('mode', 'form')
            ->set('cnpj', '56.789.012/0001-44')
            ->set('name', 'Entrega Rápida Logística Ltda')
            ->set('tradeName', 'Entrega Rápida')
            ->set('category', 'entregas')
            ->set('phone', '(24) 3388-2211')
            ->set('email', 'contato@entregarapida.com.br')
            ->call('saveCompany')
            ->assertHasNoErrors()
            ->assertSet('mode', 'detail')
            ->assertSet('selectedCompanyId', 5)
            ->assertSet('companies.4.tradeName', 'Entrega Rápida')
            ->assertSet('companies.4.providers', [])
            ->assertSee('Prestadores, documentos e autorizações permanecem separados.');
    }

    public function test_the_access_history_renders_the_p09_list(): void
    {
        $this->withoutVite();
        $this->actingAs($this->operatorWithPermissions('validacao.registrar'));

        $response = $this->get('/entradas-saidas');

        $response
            ->assertOk()
            ->assertSee('Entradas e saídas')
            ->assertSee('Luciana Ferraz')
            ->assertSee('SRA-20260810-004181')
            ->assertSee('Registros no filtro');
    }

    public function test_the_access_history_filters_by_type_and_result(): void
    {
        Livewire::test(AccessHistory::class)
            ->assertSee('Eduardo Nogueira')
            ->assertSee('Sérgio Aparecido Luz')
            ->set('typeFilter', 'saida')
            ->assertSee('Eduardo Nogueira')
            ->assertDontSee('Sérgio Aparecido Luz')
            ->set('typeFilter', 'todos')
            ->set('resultFilter', 'negado')
            ->assertSee('Bianca Moretti')
            ->assertDontSee('Eduardo Nogueira')
            ->set('resultFilter', 'todos')
            ->set('search', 'Bianca')
            ->assertSee('Bianca Moretti')
            ->assertDontSee('Eduardo Nogueira');
    }

    public function test_the_access_history_detail_explains_denied_and_pending_results(): void
    {
        Livewire::test(AccessHistory::class)
            ->call('openEntry', 3)
            ->assertSet('mode', 'detail')
            ->assertSee('Bianca Moretti')
            ->assertSee('Motivo da negação')
            ->assertSee('Responsável não localizado para confirmar a visita.')
            ->assertSee('não pode ser editado')
            ->call('backToList')
            ->assertSet('mode', 'list')
            ->call('openEntry', 1)
            ->assertSee('Aguardando conferência');
    }

    public function test_the_cash_register_renders_the_p14_open_state(): void
    {
        $this->withoutVite();
        $this->actingAs($this->operatorWithPermissions('caixa.proprio.gerenciar'));

        $response = $this->get('/caixa');

        $response
            ->assertOk()
            ->assertSee('Caixa')
            ->assertSee('Caixa aberto')
            ->assertSee('Registrar movimentação')
            ->assertSee('Histórico de caixas')
            ->assertSee('Contribuição — visitante Camila Andrade');
    }

    public function test_the_cash_register_records_a_manual_movement(): void
    {
        Livewire::test(CashRegister::class)
            ->set('movementType', 'saida')
            ->set('movementAmount', '25,00')
            ->set('movementMethod', 'dinheiro')
            ->set('movementDescription', 'Troco para portão de serviço')
            ->call('registerMovement')
            ->assertHasNoErrors()
            ->assertSee('Movimentação registrada')
            ->assertSee('Troco para portão de serviço');
    }

    public function test_the_cash_register_blocks_movement_when_closed_and_reopens(): void
    {
        Livewire::test(CashRegister::class)
            ->set('informedAmount', '50,00')
            ->call('closeRegister')
            ->assertSet('status', 'fechado')
            ->assertSee('Caixa fechado')
            ->call('registerMovement')
            ->assertSet('feedback.variant', 'danger')
            ->assertSee('RN-084')
            ->set('openingBalanceInput', '200')
            ->call('openRegister')
            ->assertSet('status', 'aberto')
            ->assertSet('movements', []);
    }

    public function test_the_cash_register_closing_computes_the_difference(): void
    {
        Livewire::test(CashRegister::class)
            ->assertSet('status', 'aberto')
            ->set('informedAmount', '240,00')
            ->call('closeRegister')
            ->assertHasNoErrors()
            ->assertSet('status', 'fechado')
            ->assertSee('diferença de R$');
    }

    public function test_the_cash_register_rejects_a_zero_value_movement(): void
    {
        Livewire::test(CashRegister::class)
            ->set('movementType', 'entrada')
            ->set('movementAmount', '0,00')
            ->set('movementMethod', 'dinheiro')
            ->set('movementDescription', 'Tentativa inválida')
            ->call('registerMovement')
            ->assertHasErrors(['movementAmount'])
            ->assertSee('O valor deve ser maior que zero.');
    }

    public function test_the_package_management_renders_the_p15_list(): void
    {
        $this->withoutVite();
        $this->actingAs($this->operatorWithPermissions('encomendas.registrar'));

        $response = $this->get('/encomendas');

        $response
            ->assertOk()
            ->assertSee('Encomendas')
            ->assertSee('Bianca Moretti')
            ->assertSee('SRE-20260810-0042')
            ->assertSee('Aguardando retirada')
            ->assertSee('Registrar encomenda');
    }

    public function test_the_package_detail_notifies_and_delivers(): void
    {
        Livewire::test(PackageManagement::class)
            ->call('openPackage', 3)
            ->assertSet('mode', 'detail')
            ->assertSet('selectedPackageId', 3)
            ->assertSee('Rafael Domingues')
            ->call('notifyRecipient')
            ->assertSet('packages.2.status', 'avisado')
            ->assertSee('Morador avisado')
            ->set('deliveredTo', 'Rafael Domingues')
            ->call('deliverPackage')
            ->assertSet('packages.2.status', 'entregue')
            ->assertSet('packages.2.deliveredTo', 'Rafael Domingues')
            ->assertSee('Entrega registrada');
    }

    public function test_the_package_form_creates_a_demo_package_awaiting_notification(): void
    {
        Livewire::test(PackageManagement::class)
            ->call('createPackage')
            ->assertSet('mode', 'form')
            ->set('recipientName', 'Camila Andrade')
            ->set('property', 'Bloco B — Apto 304')
            ->set('carrier', 'Shopee')
            ->set('type', 'caixa')
            ->set('storageLocation', 'Prateleira A1')
            ->call('savePackage')
            ->assertHasNoErrors()
            ->assertSet('mode', 'detail')
            ->assertSet('selectedPackageId', 6)
            ->assertSet('packages.5.status', 'aguardando')
            ->assertSet('packages.5.notifiedAt', null)
            ->assertSee('O morador ainda não foi avisado.');
    }

    public function test_the_public_pre_registration_welcome_reflects_a_tourist_stay(): void
    {
        $this->withoutVite();

        $response = $this->get('/pre-cadastro/convite-demonstracao');

        $response
            ->assertOk()
            ->assertSee('Turista')
            ->assertSee('estadia')
            ->assertSee('praia');

        Livewire::test(PublicPreRegistration::class)
            ->assertSet('accessType', 'turista');
    }

    public function test_the_property_zip_code_lookup_fills_the_address(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response([
                'logradouro' => 'Rua das Acácias',
                'bairro' => 'Jardim das Flores',
                'localidade' => 'Taubaté',
                'uf' => 'SP',
            ]),
        ]);

        Livewire::test(PropertyManagement::class)
            ->call('createProperty')
            ->set('zipCode', '12000-000')
            ->call('lookupZipCode')
            ->assertSet('street', 'Rua das Acácias')
            ->assertSet('district', 'Jardim das Flores')
            ->assertSet('city', 'Taubaté')
            ->assertSet('state', 'SP')
            ->assertSet('zipCodeLookupFailed', false);
    }

    public function test_the_property_zip_code_lookup_warns_when_not_found(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response(['erro' => true]),
        ]);

        Livewire::test(PropertyManagement::class)
            ->call('createProperty')
            ->set('zipCode', '00000-000')
            ->call('lookupZipCode')
            ->assertSet('zipCodeLookupFailed', true)
            ->assertSee('CEP não encontrado');
    }

    public function test_the_public_pre_registration_zip_code_lookup_fills_the_address(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response([
                'logradouro' => 'Rua das Palmeiras',
                'bairro' => 'Centro',
                'localidade' => 'Taubaté',
                'uf' => 'SP',
            ]),
        ]);

        Livewire::test(PublicPreRegistration::class)
            ->set('zipCode', '12010-000')
            ->call('lookupZipCode')
            ->assertSet('address', 'Rua das Palmeiras')
            ->assertSet('district', 'Centro')
            ->assertSet('city', 'Taubaté')
            ->assertSet('state', 'SP')
            ->assertSet('zipCodeLookupFailed', false);
    }

    public function test_the_public_pre_registration_zip_code_lookup_warns_when_not_found(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response(['erro' => true]),
        ]);

        Livewire::test(PublicPreRegistration::class)
            ->call('start')
            ->set('step', 2)
            ->set('zipCode', '00000-000')
            ->call('lookupZipCode')
            ->assertSet('zipCodeLookupFailed', true)
            ->assertSee('CEP não encontrado');
    }

    public function test_the_public_pre_registration_destination_defaults_to_the_beach_for_tourists(): void
    {
        Livewire::test(PublicPreRegistration::class)
            ->call('start')
            ->assertSet('accessType', 'turista')
            ->set('step', 2)
            ->assertSee('Praia do Santa Rita')
            ->assertDontSee('Imóvel de destino');
    }

    public function test_the_public_pre_registration_asks_for_the_property_when_visitor(): void
    {
        Livewire::test(PublicPreRegistration::class)
            ->call('start')
            ->set('accessType', 'visitante')
            ->set('step', 2)
            ->assertDontSee('Praia do Santa Rita')
            ->assertSee('Imóvel de destino')
            ->assertSee('Responsável: Mariana Souza')
            ->set('destinationProperty', 'Bloco A — Apto 208')
            ->assertSee('Responsável: Bianca Moretti');
    }
}
