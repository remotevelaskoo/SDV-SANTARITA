<?php

namespace Tests\Feature;

use App\Livewire\AccessValidation;
use App\Livewire\PreRegistrationReview;
use App\Models\Arquivo;
use App\Models\AutorizacaoAcesso;
use App\Models\HistoricoAcesso;
use App\Models\Implantacao;
use App\Models\Pessoa;
use App\Models\PessoaDocumento;
use App\Models\PreRegistration;
use App\Models\PreRegistrationArquivo;
use App\Services\PrivateFileService;
use App\Support\ImplantacaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\ImagensPessoaSinteticas;
use Tests\Support\OperadorComPermissoes;
use Tests\TestCase;

/**
 * Pedido do PO (13/11/2026): o pré-cadastro, por enquanto, é só de turista.
 * A aprovação pela portaria cria a pessoa, que fica no sistema. No "Liberar
 * entrada" o turista ganha 24 h, enviadas ao terminal facial (não à cancela);
 * quando volta, é achado na busca e já vem como Turista para mais 24 h.
 */
class PreCadastroCriaPessoaTest extends TestCase
{
    use OperadorComPermissoes, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        Storage::fake('private-files');
    }

    protected function tearDown(): void
    {
        ImplantacaoContext::forgetCurrent();
        parent::tearDown();
    }

    private function turista(array $dados = []): PreRegistration
    {
        return PreRegistration::factory()->tourist()->create([
            'name' => 'Gabriel Sintético',
            'document' => '529.982.247-25',
            'phone' => '(12) 99999-0000',
            'email' => 'gabriel@example.test',
            'vehicle_plate' => 'GEO8G49',
            ...$dados,
        ]);
    }

    private function comSelfie(PreRegistration $registro): void
    {
        $caminho = tempnam(sys_get_temp_dir(), 'selfie');
        file_put_contents($caminho, ImagensPessoaSinteticas::boa());
        app(PrivateFileService::class)->storePreRegistrationFiles($registro, ['selfie' => new UploadedFile($caminho, 'selfie.jpg', 'image/jpeg', null, true)]);
    }

    public function test_aprovar_turista_cria_a_pessoa_com_selfie_sem_autorizacao(): void
    {
        $registro = $this->turista();
        $this->comSelfie($registro);

        Livewire::actingAs($this->operador('pre-registro.analisar', 'pre-registro.aprovar'))
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->call('approve')
            ->assertHasNoErrors()
            ->assertSee('Pré-cadastro aprovado e pessoa cadastrada');

        $pessoa = Pessoa::query()->sole();
        $this->assertSame('Gabriel Sintético', $pessoa->nome);
        $this->assertSame('52998224725', PessoaDocumento::query()->where('pessoa_id', $pessoa->id)->value('valor_normalizado'));
        $this->assertSame('pre_cadastro_aprovado', $pessoa->fotoVigente()?->file->origin);
        $this->assertSame('aprovado', $registro->fresh()->status);
        $this->assertSame(0, AutorizacaoAcesso::query()->count(), 'Aprovar não gasta as 24 h: elas começam no Liberar entrada.');
    }

    public function test_cpf_ja_cadastrado_reaproveita_a_pessoa(): void
    {
        $existente = Pessoa::factory()->create(['nome' => 'Gabriel Antigo']);
        PessoaDocumento::factory()->for($existente, 'pessoa')->create(['valor_normalizado' => '52998224725', 'valor_apresentacao' => '529.982.247-25']);

        Livewire::actingAs($this->operador('pre-registro.analisar', 'pre-registro.aprovar'))
            ->test(PreRegistrationReview::class, ['preRegistration' => $this->turista()->id])
            ->call('approve')
            ->assertHasNoErrors();

        $this->assertSame(1, Pessoa::query()->count());
        $this->assertSame('Gabriel Antigo', $existente->fresh()->nome, 'Nada é sobrescrito.');
    }

    public function test_so_turista_pode_ser_aprovado(): void
    {
        $registro = PreRegistration::factory()->visitor()->create(['document' => '529.982.247-25']);

        Livewire::actingAs($this->operador('pre-registro.analisar', 'pre-registro.aprovar'))
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->assertSee('Não pode ser aprovado assim')
            ->call('approve')
            ->assertHasErrors('state');

        $this->assertNotSame('aprovado', $registro->fresh()->status);
        $this->assertSame(0, Pessoa::query()->count());
    }

    public function test_liberar_turista_do_pre_cadastro_grava_24_horas_e_tenta_o_terminal_facial(): void
    {
        $registro = $this->turista();
        $this->comSelfie($registro);
        Livewire::actingAs($this->operador('pre-registro.analisar', 'pre-registro.aprovar'))
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])->call('approve');

        // Porteiro sem terminal facial no posto: a autorização fica gravada e o envio aparece como pendente.
        $tela = Livewire::withQueryParams(['pre-cadastro' => $registro->id])
            ->actingAs($this->operador('validacao.registrar', 'pessoas.gerenciar', 'facial.enviar'))
            ->test(AccessValidation::class)
            ->assertSet('quickRegistrationOpen', false)
            ->assertSet('tipoAtendimento', 'turista')
            ->assertSet('placaInformada', 'GEO8G49')
            ->call('continuar')
            ->assertSet('passo', 'foto')
            ->call('continuar')
            ->assertSet('passo', 'placa')
            ->call('continuar')
            ->assertSet('passo', 'pagamento')
            ->set('contribution', 'no')
            ->call('continuar')
            ->assertSee('enviada ao terminal facial')
            ->call('concluir', 'liberar')
            ->assertHasNoErrors()
            ->assertSee('Envio ao terminal facial pendente');

        $autorizacao = AutorizacaoAcesso::query()->sole();
        $this->assertSame(AutorizacaoAcesso::TIPO_TURISTA, $autorizacao->tipo);
        $this->assertEqualsWithDelta(24 * 60, $autorizacao->inicio->diffInMinutes($autorizacao->fim), 1);
        $this->assertSame('liberado', HistoricoAcesso::query()->sole()->resultado);
        $this->assertNotNull($tela->get('envioFacialLiberacao'));
    }

    public function test_turista_que_volta_e_achado_na_busca_e_ja_vem_como_turista(): void
    {
        $registro = $this->turista();
        Livewire::actingAs($this->operador('pre-registro.analisar', 'pre-registro.aprovar'))
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])->call('approve');
        $pessoa = Pessoa::query()->sole();

        // Visita anterior já vencida.
        AutorizacaoAcesso::query()->create([
            'pessoa_id' => $pessoa->id, 'tipo' => AutorizacaoAcesso::TIPO_TURISTA,
            'inicio' => now()->subDays(3), 'fim' => now()->subDays(2), 'status' => 'expirada',
        ]);

        $tela = Livewire::actingAs($this->operador('validacao.registrar', 'pessoas.gerenciar'))
            ->test(AccessValidation::class)
            ->set('busca', 'Gabriel');
        $this->assertSame([$pessoa->id], array_column($tela->instance()->resultadosBusca()['resultados'], 'id'));

        $tela->call('selecionarPessoa', $pessoa->id)
            ->assertSet('tipoAtendimento', 'turista');
    }

    public function test_comando_cria_as_pessoas_dos_ja_aprovados(): void
    {
        $this->turista(['status' => 'aprovado']);
        PreRegistration::factory()->visitor()->create(['status' => 'aprovado', 'document' => '111.444.777-35']);

        $this->artisan('pre-cadastro:criar-pessoas-aprovados')->assertSuccessful();
        $this->artisan('pre-cadastro:criar-pessoas-aprovados')->assertSuccessful();

        $this->assertSame(['Gabriel Sintético'], Pessoa::query()->pluck('nome')->all(), 'Só turista, e uma vez só.');
    }
}
