<?php

namespace Tests\Feature\Aj006;

use App\Integracoes\Adaptadores\Simulador\CenarioSimulador;
use App\Integracoes\Dominio\Enums\EstadoSincronizacao;
use App\Integracoes\Dominio\Enums\OperacaoIntegracao;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Livewire\PersonRegistration;
use App\Models\AuditoriaEvento;
use App\Models\CredencialSincronizacao;
use App\Models\Equipamento;
use App\Models\Imovel;
use App\Models\OperacaoIntegracao as Operacao;
use App\Models\Pessoa;
use App\Models\User;
use App\Models\Vinculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\TestCase;

/**
 * AJ-006, fatia 6 — ações finais do cadastro e envio para a facial.
 * Somente o simulador: nenhuma pessoa real vai a um terminal.
 */
class EnvioFacialTest extends TestCase
{
    use MontaCenarioIntegracao, RefreshDatabase;

    private Equipamento $terminal;

    private User $operador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararIntegracao();
        $this->terminal = $this->terminalAtivo(['nome' => 'Facial entrada']);
        $this->usarFilaReal();
        $this->operador = $this->usuarioCom('pessoas.gerenciar', 'facial.enviar');
        Imovel::factory()->create(['codigo' => 'SRA-A-102']);
    }

    protected function tearDown(): void
    {
        $this->encerrarIntegracao();
        parent::tearDown();
    }

    public function test_salvar_e_enviar_salva_primeiro_e_mostra_os_dois_resultados(): void
    {
        $tela = $this->formulario()
            ->set('terminalId', $this->terminal->id)
            ->set('terminalConfirmado', true)
            ->call('saveAndSend')
            ->assertHasNoErrors()
            ->assertSet('feedback.variant', 'success')
            ->assertSet('resultadoEnvio.variant', 'success')
            ->assertSet('resultadoEnvio.title', 'Pessoa cadastrada na facial')
            ->assertSee('Pessoa e vínculo salvos')
            ->assertSee('Confirmado pelo terminal.');

        $pessoa = Pessoa::query()->sole();
        $this->assertSame($pessoa->id, $tela->get('pessoaId'));
        $sincronizacao = CredencialSincronizacao::query()->sole();
        $this->assertSame(EstadoSincronizacao::Sincronizado, $sincronizacao->estado);
        $this->assertCount(1, $this->simulador()->credenciaisGravadas($this->terminal->id));

        // Cadastro no terminal, sem foto nem template e sem abertura de relé.
        $payload = Operacao::query()->where('operacao', OperacaoIntegracao::SincronizarCredencial->value)->sole()->payload;
        $this->assertSame('cadastro_pessoa', $payload['tipo']);
        $this->assertSame($pessoa->id, $payload['titular_referencia']);
        $this->assertSame(0, Operacao::query()->where('operacao', OperacaoIntegracao::AberturaRemota->value)->count());
        $this->assertSame(0, $this->simulador()->totalAberturasFisicas($this->terminal->id));

        $auditoria = AuditoriaEvento::query()->where('action', 'facial_envio_solicitado')->sole();
        $this->assertStringNotContainsString('Pessoa Sintética', json_encode($auditoria->toArray()));
    }

    public function test_falha_da_facial_nao_desfaz_o_salvamento(): void
    {
        $this->simulador()->definirCenario($this->terminal->id, CenarioSimulador::Recusa, OperacaoIntegracao::SincronizarCredencial);

        $this->formulario()
            ->set('terminalId', $this->terminal->id)
            ->set('terminalConfirmado', true)
            ->call('saveAndSend')
            ->assertSet('feedback.variant', 'success')
            ->assertSet('resultadoEnvio.variant', 'danger')
            ->assertSee('O cadastro no SDV continua salvo.');

        $this->assertSame(1, Pessoa::query()->count());
        $this->assertSame(1, Vinculo::query()->count());
        $this->assertSame(EstadoSincronizacao::Falha, CredencialSincronizacao::query()->sole()->estado);
    }

    public function test_sem_terminal_confirmado_nada_e_salvo_nem_enviado(): void
    {
        $this->formulario()
            ->set('terminalId', $this->terminal->id)
            ->call('saveAndSend')
            ->assertHasErrors('terminalConfirmado');

        $this->formulario()
            ->set('terminalId', '')
            ->set('terminalConfirmado', true)
            ->call('saveAndSend')
            ->assertHasErrors('terminalId');

        $this->assertSame(0, Pessoa::query()->count());
        $this->assertSame(0, Operacao::query()->where('operacao', OperacaoIntegracao::SincronizarCredencial->value)->count());
    }

    public function test_enviar_exige_pessoa_salva_e_bloqueia_alteracoes_pendentes(): void
    {
        $tela = $this->formulario()
            ->set('terminalId', $this->terminal->id)
            ->set('terminalConfirmado', true)
            ->call('send')
            ->assertHasErrors('envio')
            ->assertSee('Salve o cadastro antes de enviar para a facial.');

        $tela->call('activate')->assertSet('feedback.variant', 'success');

        // Alteração não salva: nada de salvamento silencioso.
        $tela->set('fullName', 'Pessoa Sintética Alterada')
            ->set('terminalConfirmado', true)
            ->call('send')
            ->assertHasErrors('envio')
            ->assertSee('Há alterações não salvas.');
        $this->assertSame('Pessoa Sintética', Pessoa::query()->sole()->nome);
        $this->assertSame(0, CredencialSincronizacao::query()->count());

        $tela->call('discardChanges')
            ->assertSet('fullName', 'Pessoa Sintética')
            ->set('terminalConfirmado', true)
            ->call('send')
            ->assertHasNoErrors()
            ->assertSet('resultadoEnvio.variant', 'success');
    }

    public function test_salvar_alteracoes_atualiza_a_pessoa_sem_duplicar(): void
    {
        $tela = $this->formulario()->call('activate');

        $tela->set('fullName', 'Pessoa Sintética Corrigida')
            ->set('phone', '(24) 98888-1111')
            ->call('activate')
            ->assertSet('feedback.title', 'Alterações salvas');

        $pessoa = Pessoa::query()->sole();
        $this->assertSame('Pessoa Sintética Corrigida', $pessoa->nome);
        $this->assertSame(2, $pessoa->versao);
        // Contato antigo encerrado, não apagado.
        $this->assertSame(2, $pessoa->contatos()->where('tipo', 'telefone')->count());
        $this->assertSame(1, $pessoa->contatos()->where('tipo', 'telefone')->whereNull('ended_at')->count());

        $tela->set('document', '999.999.999-99')->call('activate')->assertHasErrors('document');
        $evento = AuditoriaEvento::query()->where('action', 'pessoa_atualizada')->sole();
        $this->assertStringNotContainsString('Corrigida', json_encode($evento->toArray()));
    }

    public function test_enviar_duas_vezes_nao_repete_o_comando(): void
    {
        $tela = $this->formulario()
            ->set('terminalId', $this->terminal->id)
            ->set('terminalConfirmado', true)
            ->call('saveAndSend');

        $tela->set('terminalConfirmado', true)
            ->call('send')
            ->assertSee('Este envio já tinha sido pedido e não foi repetido.');

        $this->assertSame(1, Operacao::query()->where('operacao', OperacaoIntegracao::SincronizarCredencial->value)->count());
        $this->assertSame(1, $this->simulador()->chamadas($this->terminal->id, OperacaoIntegracao::SincronizarCredencial));
    }

    public function test_depois_de_uma_falha_um_novo_envio_e_um_novo_pedido(): void
    {
        $this->simulador()->definirCenario($this->terminal->id, CenarioSimulador::Recusa, OperacaoIntegracao::SincronizarCredencial);
        $tela = $this->formulario()
            ->set('terminalId', $this->terminal->id)
            ->set('terminalConfirmado', true)
            ->call('saveAndSend');

        $this->simulador()->definirCenario($this->terminal->id, CenarioSimulador::Sucesso, OperacaoIntegracao::SincronizarCredencial);
        $tela->set('terminalConfirmado', true)->call('send')->assertSet('resultadoEnvio.variant', 'success');

        $this->assertSame(2, Operacao::query()->where('operacao', OperacaoIntegracao::SincronizarCredencial->value)->count());
        $this->assertSame(EstadoSincronizacao::Sincronizado, CredencialSincronizacao::query()->sole()->estado);
    }

    public function test_cancelar_encerra_so_o_atendimento_e_mantem_o_que_foi_salvo(): void
    {
        $tela = $this->formulario()->call('activate');
        $tela->set('fullName', 'Rascunho Descartado')
            ->call('cancel')
            ->assertSet('pessoaId', null)
            ->assertSet('fullName', '')
            ->assertSee('O cadastro salvo foi mantido.');

        $this->assertSame('Pessoa Sintética', Pessoa::query()->sole()->nome);
        $this->assertSame(1, Vinculo::query()->count());
    }

    public function test_sem_permissao_nao_ve_nem_envia_e_terminal_inativo_nao_e_oferecido(): void
    {
        $semPermissao = $this->usuarioCom('pessoas.gerenciar');
        Livewire::actingAs($semPermissao)->test(PersonRegistration::class)
            ->assertDontSee('Salvar e enviar para a facial')
            ->set('terminalId', $this->terminal->id)
            ->set('terminalConfirmado', true)
            ->call('send')
            ->assertForbidden();

        $this->cadastro()->alterarStatus($this->terminal, StatusEquipamento::Manutencao, 'manutenção programada');
        $this->formulario()
            ->assertSee('Nenhum terminal disponível')
            ->set('terminalId', $this->terminal->id)
            ->set('terminalConfirmado', true)
            ->call('saveAndSend')
            ->assertHasErrors('terminalId');
        $this->assertSame(0, Pessoa::query()->count());
    }

    public function test_com_um_unico_terminal_ele_ja_vem_escolhido_mas_exige_confirmacao(): void
    {
        $this->formulario()
            ->assertSet('terminalId', $this->terminal->id)
            ->assertSet('terminalConfirmado', false)
            ->assertSee('Facial entrada');
    }

    private function formulario(): Testable
    {
        return Livewire::actingAs($this->operador)->test(PersonRegistration::class)
            ->set('fullName', 'Pessoa Sintética')
            ->set('document', '222.333.444-55')
            ->set('birthDate', '1990-05-14')
            ->set('phone', '(24) 99988-7766')
            ->set('property', 'SRA-A-102')
            ->set('startDate', now()->format('Y-m-d'));
    }
}
