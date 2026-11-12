<?php

namespace Tests\Feature;

use App\Livewire\AccessValidation;
use App\Livewire\PreRegistrationReview;
use App\Models\AuditoriaEvento;
use App\Models\AutorizacaoAcesso;
use App\Models\Imovel;
use App\Models\Implantacao;
use App\Models\Pessoa;
use App\Models\PessoaDocumento;
use App\Models\PreRegistration;
use App\Support\ImplantacaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\DestinosDeTeste;
use Tests\Support\OperadorComPermissoes;
use Tests\TestCase;

/**
 * Pedido do PO (12/11/2026): na análise, "Aprovar e validar entrada" aprova e
 * já abre a Validação de entrada com a pessoa, sem trocar de menu nem buscar.
 * Aprovar continua sem liberar entrada, sem criar autorização e sem facial.
 */
class PreCadastroAprovarEValidarTest extends TestCase
{
    use OperadorComPermissoes, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        Storage::fake('private-files');
        DestinosDeTeste::criar(['Bloco B — Apto 304' => 'Mariana Souza']);
    }

    protected function tearDown(): void
    {
        ImplantacaoContext::forgetCurrent();
        parent::tearDown();
    }

    private function preCadastro(array $dados = []): PreRegistration
    {
        return PreRegistration::factory()->visitor('Bloco B — Apto 304')->create([
            'name' => 'Paula Sintética',
            'document' => '123.456.789-09',
            'phone' => '(11) 98888-7777',
            'responsible_name' => 'Mariana Souza',
            'vehicle_plate' => 'ABC1D23',
            ...$dados,
        ]);
    }

    public function test_aprovar_e_validar_aprova_e_abre_a_validacao_com_a_pessoa(): void
    {
        $registro = $this->preCadastro();
        $gestor = $this->operador('pre-registro.analisar', 'pre-registro.aprovar', 'validacao.registrar');

        Livewire::actingAs($gestor)->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->assertSee('Aprovar e validar entrada')
            ->call('approveAndValidate')
            ->assertRedirect(route('validation', ['pre-cadastro' => $registro->id]));

        $this->assertSame('aprovado', $registro->fresh()->status);
        $this->assertSame(0, AutorizacaoAcesso::query()->count());
    }

    public function test_sem_permissao_de_validacao_o_botao_nao_aparece_e_a_acao_e_recusada(): void
    {
        $registro = $this->preCadastro();
        $analista = $this->operador('pre-registro.analisar', 'pre-registro.aprovar');

        Livewire::actingAs($analista)->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->assertDontSee('Aprovar e validar entrada')
            ->call('approveAndValidate')
            ->assertForbidden();

        $this->assertSame('aguardando', $registro->fresh()->status);
    }

    public function test_cpf_sem_cadastro_abre_o_cadastro_rapido_preenchido_para_conferencia(): void
    {
        $registro = $this->preCadastro();
        $registro->update(['status' => 'aprovado']);
        $codigo = Imovel::query()->sole()->codigo;

        Livewire::withQueryParams(['pre-cadastro' => $registro->id])
            ->actingAs($this->operador('validacao.registrar', 'pessoas.gerenciar'))
            ->test(AccessValidation::class)
            ->assertSet('quickRegistrationOpen', true)
            ->assertSet('quickEtapa', 'conferencia')
            ->assertSet('quickName', 'Paula Sintética')
            ->assertSet('quickDocument', '123.456.789-09')
            ->assertSet('quickAccessType', 'visitante')
            ->assertSet('quickResponsible', 'Mariana Souza')
            ->assertSet('quickPropertyCode', $codigo)
            ->assertSet('placaInformada', 'ABC1D23')
            ->assertSet('currentPessoaId', null)
            ->assertSee('Dados do pré-cadastro '.$registro->protocol);

        // Nada foi gravado: nem pessoa nova, nem autorização.
        $this->assertSame(0, PessoaDocumento::query()->where('valor_normalizado', '12345678909')->count());
        $this->assertSame(0, AutorizacaoAcesso::query()->count());
        $this->assertTrue(AuditoriaEvento::query()->where('action', 'abriu_validacao_pelo_pre_cadastro')->where('entity_id', $registro->id)->exists());
    }

    public function test_salvar_o_cadastro_conferido_nao_libera_nem_autoriza(): void
    {
        $registro = $this->preCadastro();
        $registro->update(['status' => 'aprovado']);

        Livewire::withQueryParams(['pre-cadastro' => $registro->id])
            ->actingAs($this->operador('validacao.registrar', 'pessoas.gerenciar'))
            ->test(AccessValidation::class)
            ->call('saveQuickRegistration')
            ->assertHasNoErrors()
            ->assertSet('quickPersonRegistered', true);

        $this->assertSame(1, PessoaDocumento::query()->where('valor_normalizado', '12345678909')->count());
        $this->assertSame(0, AutorizacaoAcesso::query()->count());
    }

    public function test_prestador_nao_ganha_responsavel_nem_imovel_no_cadastro_rapido(): void
    {
        $registro = $this->preCadastro(['access_type' => 'prestador', 'responsible_name' => null]);
        $registro->update(['status' => 'aprovado']);

        Livewire::withQueryParams(['pre-cadastro' => $registro->id])
            ->actingAs($this->operador('validacao.registrar', 'pessoas.gerenciar'))
            ->test(AccessValidation::class)
            ->assertSet('quickAccessType', 'prestador')
            ->assertSet('quickResponsible', '')
            ->assertSet('quickPropertyCode', '');
    }

    public function test_cpf_ja_cadastrado_abre_a_pessoa_existente(): void
    {
        $registro = $this->preCadastro();
        $registro->update(['status' => 'aprovado']);
        $pessoa = Pessoa::factory()->create(['nome' => 'Paula Sintética']);
        PessoaDocumento::factory()->for($pessoa)->create(['valor_normalizado' => '12345678909', 'valor_apresentacao' => '123.456.789-09']);

        Livewire::withQueryParams(['pre-cadastro' => $registro->id])
            ->actingAs($this->operador('validacao.registrar', 'pessoas.gerenciar'))
            ->test(AccessValidation::class)
            ->assertSet('currentPessoaId', $pessoa->id)
            ->assertSet('quickRegistrationOpen', false)
            ->assertSee('Pessoa do pré-cadastro '.$registro->protocol);
    }

    public function test_pre_cadastro_nao_aprovado_ou_de_outra_implantacao_nao_carrega_nada(): void
    {
        $aguardando = $this->preCadastro();
        $operador = $this->operador('validacao.registrar', 'pessoas.gerenciar');

        Livewire::withQueryParams(['pre-cadastro' => $aguardando->id])
            ->actingAs($operador)
            ->test(AccessValidation::class)
            ->assertSet('quickRegistrationOpen', false)
            ->assertSet('quickName', '')
            ->assertSee('Pré-cadastro não disponível');

        $outra = Implantacao::factory()->create();
        $alheio = ImplantacaoContext::executarNo($outra, fn () => PreRegistration::factory()->withStatus('aprovado')->create());

        Livewire::withQueryParams(['pre-cadastro' => $alheio->id])
            ->actingAs($operador)
            ->test(AccessValidation::class)
            ->assertSet('quickName', '')
            ->assertSee('Pré-cadastro não disponível');
    }

    public function test_ja_aprovado_mostra_atalho_para_a_validacao(): void
    {
        $registro = $this->preCadastro();
        $registro->update(['status' => 'aprovado']);

        Livewire::actingAs($this->operador('pre-registro.analisar', 'validacao.registrar'))
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->assertSee('Abrir na validação de entrada')
            ->call('openValidation')
            ->assertRedirect(route('validation', ['pre-cadastro' => $registro->id]));
    }
}
