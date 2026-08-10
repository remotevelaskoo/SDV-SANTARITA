<?php

namespace Tests\Feature;

use App\Livewire\AccessValidation;
use App\Livewire\Dashboard;
use App\Livewire\Login;
use App\Livewire\PersonRegistration;
use App\Livewire\PreRegistrationQueue;
use App\Livewire\PropertyManagement;
use App\Livewire\PublicPreRegistration;
use App\Livewire\VehicleManagement;
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

    public function test_the_access_validation_renders_the_complete_p06_journey(): void
    {
        $this->withoutVite();

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
            ->assertSet('protocol', 'PRE-SRA-2026-X7K9M2')
            ->assertSee('O protocolo não é uma autorização');
    }

    public function test_the_pre_registration_queue_renders_filters_and_analysis(): void
    {
        $this->withoutVite();

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
        Livewire::test(PreRegistrationQueue::class)
            ->call('approve', 1)
            ->assertSet('records.0.status', 'aprovado')
            ->assertSet('feedback.variant', 'success')
            ->call('requestCorrection', 2)
            ->assertSet('records.1.status', 'correcao')
            ->assertSet('feedback.variant', 'warning')
            ->call('reject', 2)
            ->assertSet('records.1.status', 'rejeitado')
            ->assertSet('feedback.variant', 'danger')
            ->assertSee('A observação interna não será enviada ao solicitante.');
    }

    public function test_the_property_management_renders_the_p11_list(): void
    {
        $this->withoutVite();

        $response = $this->get('/imoveis');

        $response
            ->assertOk()
            ->assertSee('Cadastro de imóveis')
            ->assertSee('SRA-A-102')
            ->assertSee('Responsável')
            ->assertSee('Ocupantes ativos')
            ->assertSee('Cadastrar imóvel');
    }

    public function test_the_property_detail_preserves_people_links_and_vehicles(): void
    {
        Livewire::test(PropertyManagement::class)
            ->call('openProperty', 1)
            ->assertSet('mode', 'detail')
            ->assertSet('selectedPropertyId', 1)
            ->assertSee('Pessoas e vínculos')
            ->assertSee('Responsável principal')
            ->assertSee('Marcos Vinicius da Silva')
            ->assertSee('ABC1D23')
            ->call('togglePropertyBlock')
            ->assertSet('properties.0.status', 'bloqueado')
            ->assertSet('feedback.variant', 'warning')
            ->assertSee('Os vínculos individuais continuam preservados');
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
            ->assertSet('selectedPropertyId', 6)
            ->assertSet('properties.5.code', 'SRA-D-801')
            ->assertSet('properties.5.occupants', 0)
            ->assertSet('properties.5.vehicles', 0)
            ->assertSee('Pessoas, vínculos e autorizações permanecem separados.');
    }

    public function test_the_vehicle_management_renders_the_p12_list(): void
    {
        $this->withoutVite();

        $response = $this->get('/veiculos');

        $response
            ->assertOk()
            ->assertSee('Cadastro de veículos')
            ->assertSee('ABC1D23')
            ->assertSee('Placas sincronizadas')
            ->assertSee('Cadastrar veículo');
    }

    public function test_the_vehicle_detail_preserves_links_when_blocked(): void
    {
        Livewire::test(VehicleManagement::class)
            ->call('openVehicle', 1)
            ->assertSet('mode', 'detail')
            ->assertSet('selectedVehicleId', 1)
            ->assertSee('Proprietário e vínculo')
            ->assertSee('Marcos Vinicius da Silva')
            ->assertSee('SRA-A-102')
            ->call('toggleVehicleBlock')
            ->assertSet('vehicles.0.status', 'bloqueado')
            ->assertSet('vehicles.0.owner', 'Marcos Vinicius da Silva')
            ->assertSet('feedback.variant', 'warning')
            ->assertSee('O histórico e os vínculos foram preservados');
    }

    public function test_the_vehicle_form_creates_a_demo_vehicle_without_releasing_access(): void
    {
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
            ->set('propertyCode', 'SRA-B-304')
            ->set('vehicleStatus', 'pendente')
            ->call('saveVehicle')
            ->assertHasNoErrors()
            ->assertSet('mode', 'detail')
            ->assertSet('selectedVehicleId', 6)
            ->assertSet('vehicles.5.plate', 'QRS8T90')
            ->assertSet('vehicles.5.status', 'pendente')
            ->assertSet('vehicles.5.lprStatus', 'nao_sincronizado')
            ->assertSee('Pessoa, imóvel, situação e autorização continuam independentes.');
    }

    public function test_the_vehicle_form_rejects_a_duplicate_plate(): void
    {
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

    public function test_the_person_registration_renders_the_first_step(): void
    {
        $this->withoutVite();

        $response = $this->get('/pessoas/nova');

        $response
            ->assertOk()
            ->assertSee('Cadastro de pessoa')
            ->assertSee('Protótipo demonstrativo')
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
        Livewire::test(PersonRegistration::class)
            ->set('document', '111.111.111-11')
            ->call('checkDocument')
            ->assertSet('duplicateFound', true)
            ->assertSee('Já existe uma pessoa com este documento.');
    }

    public function test_the_person_registration_completes_the_five_step_journey(): void
    {
        Livewire::test(PersonRegistration::class)
            ->set('fullName', 'Marcos Andrade Ferreira')
            ->set('document', '222.333.444-55')
            ->set('birthDate', '1990-05-14')
            ->set('phone', '(24) 99988-7766')
            ->call('nextStep')
            ->assertSet('currentStep', 2)
            ->call('nextStep')
            ->assertSet('currentStep', 3)
            ->call('nextStep')
            ->assertSet('currentStep', 4)
            ->set('startDate', '2026-08-10')
            ->call('nextStep')
            ->assertSet('currentStep', 5)
            ->call('activate')
            ->assertSet('feedback.variant', 'success')
            ->assertSee('A sincronização facial está pendente.')
            ->assertSet('protocol', fn (?string $protocol) => $protocol !== null && str_starts_with($protocol, 'SRP-'));
    }
}
