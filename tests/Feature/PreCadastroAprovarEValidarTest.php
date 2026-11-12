<?php

namespace Tests\Feature;

use App\Livewire\AccessValidation;
use App\Livewire\PreRegistrationReview;
use App\Models\AuditoriaEvento;
use App\Models\AutorizacaoAcesso;
use App\Models\Contribuicao;
use App\Models\HistoricoAcesso;
use App\Models\Imovel;
use App\Models\Implantacao;
use App\Models\Pessoa;
use App\Models\PessoaDocumento;
use App\Models\PreRegistration;
use App\Models\Vinculo;
use App\Services\PrivateFileService;
use App\Support\ImplantacaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\DestinosDeTeste;
use Tests\Support\ImagensPessoaSinteticas;
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

    private function comSelfie(PreRegistration $registro, string $conteudo): void
    {
        $caminho = tempnam(sys_get_temp_dir(), 'selfie');
        file_put_contents($caminho, $conteudo);
        app(PrivateFileService::class)->storePreRegistrationFiles($registro, ['selfie' => new UploadedFile($caminho, 'selfie.jpg', 'image/jpeg', null, true)]);
    }

    public function test_selfie_do_pre_cadastro_vira_a_foto_do_cadastro_sem_pedir_de_novo(): void
    {
        $registro = $this->preCadastro();
        $registro->update(['status' => 'aprovado']);
        $this->comSelfie($registro, ImagensPessoaSinteticas::boa());

        $tela = Livewire::withQueryParams(['pre-cadastro' => $registro->id])
            ->actingAs($this->operador('validacao.registrar', 'pessoas.gerenciar', 'arquivos.sensiveis.visualizar'))
            ->test(AccessValidation::class)
            ->assertSee('Dados conferidos: conferir foto')
            ->call('irParaEtapa', 'foto')
            ->assertSee('Selfie do pré-cadastro: será a foto do cadastro')
            ->call('saveQuickRegistration')
            ->assertHasNoErrors()
            ->assertSet('quickResultado.foto.title', 'Foto do pré-cadastro usada');

        $pessoa = Pessoa::query()->findOrFail($tela->get('currentPessoaId'));
        $foto = $pessoa->fotoVigente();
        $this->assertNotNull($foto);
        $this->assertSame('pre_cadastro_aprovado', $foto->file->origin);
        // O original continua no pré-cadastro.
        $this->assertNotNull($registro->fresh()->currentFileLink('selfie')?->file);
        $this->assertSame(0, AutorizacaoAcesso::query()->count());
    }

    public function test_selfie_ruim_nao_vira_foto_e_o_cadastro_e_salvo(): void
    {
        $registro = $this->preCadastro();
        $registro->update(['status' => 'aprovado']);
        $this->comSelfie($registro, ImagensPessoaSinteticas::escura());

        $tela = Livewire::withQueryParams(['pre-cadastro' => $registro->id])
            ->actingAs($this->operador('validacao.registrar', 'pessoas.gerenciar'))
            ->test(AccessValidation::class)
            ->call('saveQuickRegistration')
            ->assertSet('quickPersonRegistered', true)
            ->assertSet('quickResultado.foto.title', 'Selfie do pré-cadastro não usada');

        $this->assertNull(Pessoa::query()->findOrFail($tela->get('currentPessoaId'))->fotoVigente());
    }

    public function test_turista_do_pre_cadastro_mostra_a_contribuicao(): void
    {
        $registro = PreRegistration::factory()->tourist()->withStatus('aprovado')->create(['document' => '123.456.789-09']);
        $operador = $this->operador('validacao.registrar', 'pessoas.gerenciar');

        // Ainda no cadastro rápido e depois de salvo: a contribuição de turista aparece.
        Livewire::withQueryParams(['pre-cadastro' => $registro->id])
            ->actingAs($operador)
            ->test(AccessValidation::class)
            ->assertSet('quickAccessType', 'turista')
            ->assertSee('Entrada paga neste acesso')
            ->call('saveQuickRegistration')
            ->assertSet('quickPersonRegistered', true)
            ->assertDontSee('Sem contribuição');

        // Pessoa já cadastrada, sem vínculo nem visita: vale o tipo do pré-cadastro.
        Livewire::withQueryParams(['pre-cadastro' => $registro->id])
            ->actingAs($operador)
            ->test(AccessValidation::class)
            ->assertSet('quickRegistrationOpen', false)
            ->assertDontSee('Sem contribuição')
            ->assertSee('Entrada paga neste acesso');
    }

    public function test_morador_com_vinculo_nao_vira_turista_pelo_pre_cadastro(): void
    {
        $registro = PreRegistration::factory()->tourist()->withStatus('aprovado')->create(['document' => '123.456.789-09']);
        $pessoa = Pessoa::factory()->create();
        PessoaDocumento::factory()->for($pessoa)->create(['valor_normalizado' => '12345678909', 'valor_apresentacao' => '123.456.789-09']);
        Vinculo::factory()->create(['pessoa_id' => $pessoa->id, 'imovel_id' => Imovel::query()->first()->id, 'tipo' => 'morador']);

        Livewire::withQueryParams(['pre-cadastro' => $registro->id])
            ->actingAs($this->operador('validacao.registrar'))
            ->test(AccessValidation::class)
            ->assertSet('currentPessoaId', $pessoa->id)
            ->assertSee('Sem contribuição');
    }

    public function test_atendimento_registrado_nao_acusa_o_proprio_ticket_nem_grava_de_novo(): void
    {
        $registro = PreRegistration::factory()->tourist()->withStatus('aprovado')->create(['document' => '123.456.789-09']);

        $tela = Livewire::withQueryParams(['pre-cadastro' => $registro->id])
            ->actingAs($this->operador('validacao.registrar', 'pessoas.gerenciar'))
            ->test(AccessValidation::class)
            ->call('saveQuickRegistration')
            ->set('contribution', 'yes')
            ->set('ticketNumero', '7777777777')
            ->call('release')
            ->assertHasNoErrors();

        $protocolo = $tela->get('protocol');
        $this->assertNotNull($protocolo);
        $this->assertNull($tela->instance()->ticketDuplicado());
        $tela->assertDontSee('Ticket já registrado')->assertSee('Atendimento registrado: '.$protocolo);

        // Clique repetido não grava outro atendimento nem outra contribuição.
        $tela->call('release')->assertHasErrors(['atendimento']);
        $this->assertSame(1, HistoricoAcesso::query()->count());
        $this->assertSame(1, Contribuicao::query()->count());

        // Próximo atendimento com o mesmo ticket: aí sim é duplicado.
        $tela->call('startNewValidation')->set('ticketNumero', '7777777777');
        $this->assertSame($protocolo, $tela->instance()->ticketDuplicado()['protocolo']);
    }
}
