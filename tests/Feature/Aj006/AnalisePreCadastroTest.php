<?php

namespace Tests\Feature\Aj006;

use App\Livewire\PreRegistrationQueue;
use App\Livewire\PreRegistrationReview;
use App\Models\AuditoriaEvento;
use App\Models\Implantacao;
use App\Models\PreRegistration;
use App\Services\PrivateFileService;
use App\Support\ComparacaoOcrPreCadastro;
use App\Support\ImplantacaoContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\OperadorComPermissoes;
use Tests\TestCase;

/** AJ-006, fatia 3 — análise do pré-cadastro em página própria. */
class AnalisePreCadastroTest extends TestCase
{
    use OperadorComPermissoes, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        Storage::fake(PrivateFileService::DISK);
    }

    protected function tearDown(): void
    {
        ImplantacaoContext::forgetCurrent();
        parent::tearDown();
    }

    public function test_pagina_exige_permissao_de_analise(): void
    {
        $registro = PreRegistration::factory()->create();

        $this->actingAs($this->operador('validacao.registrar'))
            ->get(route('pre-registrations.review', $registro->id))
            ->assertRedirect();

        Livewire::actingAs($this->operador('validacao.registrar'))
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->assertForbidden();
    }

    public function test_pre_cadastro_de_outra_implantacao_nao_abre(): void
    {
        $atual = ImplantacaoContext::current();
        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        $outro = PreRegistration::factory()->create();
        ImplantacaoContext::setCurrentForTesting($atual);

        $this->expectException(ModelNotFoundException::class);
        Livewire::actingAs($this->operador('pre-registro.analisar'))
            ->test(PreRegistrationReview::class, ['preRegistration' => $outro->id]);
    }

    public function test_cada_decisao_exige_a_permissao_especifica_no_backend(): void
    {
        $registro = PreRegistration::factory()->create(['status' => 'aguardando']);
        $consulta = $this->operador('pre-registro.analisar');

        Livewire::actingAs($consulta)
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->assertDontSee('Aprovar pré-cadastro')
            ->assertDontSee('Solicitar correção')
            ->assertSee('Seu perfil permite consultar, mas não decidir este pré-cadastro.')
            ->call('approve')
            ->assertForbidden();

        Livewire::actingAs($consulta)
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->call('reject')
            ->assertForbidden();

        Livewire::actingAs($consulta)
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->call('requestCorrection')
            ->assertForbidden();

        $this->assertSame('aguardando', $registro->fresh()->status);
        $this->assertSame(1, $registro->fresh()->version);
    }

    public function test_decisao_registrada_nao_aceita_outra_de_aba_desatualizada(): void
    {
        $registro = PreRegistration::factory()->create(['status' => 'aguardando']);
        $gestor = $this->operador('pre-registro.analisar', 'pre-registro.aprovar', 'pre-registro.rejeitar');

        $abaAntiga = Livewire::actingAs($gestor)->test(PreRegistrationReview::class, ['preRegistration' => $registro->id]);
        Livewire::actingAs($gestor)->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])->call('approve');

        $abaAntiga->call('reject')->assertHasErrors('state')->assertSet('feedback', null);

        $this->assertSame('aprovado', $registro->fresh()->status);
    }

    public function test_mostra_as_quatro_imagens_somente_apos_clique_e_com_contexto_auditado(): void
    {
        $registro = PreRegistration::factory()->create();
        $arquivos = app(PrivateFileService::class)->storePreRegistrationFiles($registro, [
            'selfie' => UploadedFile::fake()->image('selfie.jpg', 480, 640),
            'documento' => UploadedFile::fake()->image('frente.jpg', 1200, 800),
            'documento_verso' => UploadedFile::fake()->image('verso.jpg', 1200, 800),
        ]);
        $operador = $this->operador('pre-registro.analisar', 'arquivos.sensiveis.visualizar');

        $teste = Livewire::actingAs($operador)
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->assertSee('Exibir imagens para conferência')
            ->assertSee('Selfie')
            ->assertSee('Documento (frente)')
            ->assertSee('Documento (verso)')
            ->assertSee('Foto recortada do documento')
            ->assertSee('Não enviado neste pré-cadastro');

        foreach ($arquivos as $arquivo) {
            $teste->assertSee($arquivo->id, escape: false)
                ->assertDontSee('src="'.route('protected-files.show', $arquivo), escape: false);
        }

        $url = route('protected-files.show', ['arquivo' => $arquivos[0], 'contexto' => 'analise_pre_cadastro']);
        $teste->assertSee(str_replace('/', '\/', $url), escape: false);

        $this->actingAs($operador)->get($url)->assertOk();
        $evento = AuditoriaEvento::query()->where('action', 'visualizou_arquivo_protegido')->sole();
        $this->assertSame('analise_pre_cadastro', $evento->context->metadata['contexto']);
    }

    public function test_sem_permissao_de_arquivo_nao_expoe_imagens(): void
    {
        $registro = PreRegistration::factory()->create();
        [$selfie] = app(PrivateFileService::class)->storePreRegistrationFiles($registro, [
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
        ]);

        Livewire::actingAs($this->operador('pre-registro.analisar'))
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->assertSee('Imagens protegidas pelo seu perfil')
            ->assertDontSee('Exibir imagens para conferência')
            ->assertDontSee($selfie->id, escape: false);
    }

    public function test_compara_leitura_automatica_confirmado_e_atual_com_documento_mascarado(): void
    {
        $registro = PreRegistration::factory()->create([
            'name' => 'Marina Sintética Teste',
            'document' => '123.456.789-09',
            'birth_date' => '1990-05-04',
        ]);
        $registro->documentExtractions()->create([
            'document_type' => 'rg_cin',
            'engine' => 'teste',
            'model_version' => 'sintetico',
            'status' => 'confirmed',
            'extracted_fields' => ['name' => 'MARINA SINTETICA TESTE', 'cpf' => '123.456.789-09', 'birth_date' => '1990-05-04', 'document_number' => '12.345.678-9'],
            'field_confidences' => ['name' => 0.93, 'cpf' => 0.97, 'birth_date' => 0.41, 'document_number' => 0.88],
            'confirmed_fields' => ['name' => 'Marina Sintética Teste', 'cpf' => '123.456.789-00', 'birth_date' => '1990-05-04', 'document_number' => '12.345.678-9'],
            'processed_at' => now(),
            'confirmed_at' => now(),
        ]);

        $linhas = collect(ComparacaoOcrPreCadastro::linhas($registro->fresh(), $registro->documentExtractions()->first()))->keyBy('campo');
        $this->assertSame('Confere', $linhas['name']['situacao'], 'acento e caixa não contam como divergência');
        $this->assertSame('Alterado pelo solicitante', $linhas['cpf']['situacao']);
        $this->assertSame('Confiança baixa', $linhas['birth_date']['situacao']);
        $this->assertSame('04/05/1990', $linhas['birth_date']['extraido']);
        $this->assertSame('Confere', $linhas['document_number']['situacao']);

        $operador = $this->operador('pre-registro.analisar', 'dados-sensiveis.revelar');
        Livewire::actingAs($operador)
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->assertSee('Leitura automática do documento')
            ->assertSee('Alterado pelo solicitante')
            ->assertSee('41%')
            ->assertSee('2 campos para conferir')
            ->assertSee('***.***.789-**')
            ->assertDontSee('123.456.789-09')
            ->assertDontSee('12.345.678-9')
            ->call('toggleDocumentReveal')
            ->assertSee('123.456.789-09')
            ->assertSee('12.345.678-9');

        $this->assertSame(1, AuditoriaEvento::query()->where('action', 'revelou_dado_sensivel')->count());
    }

    public function test_sem_leitura_automatica_orienta_conferencia_manual(): void
    {
        $registro = PreRegistration::factory()->create();

        Livewire::actingAs($this->operador('pre-registro.analisar'))
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->assertSee('Sem leitura automática')
            ->assertSee('Sem selfie');
    }

    public function test_fila_leva_para_a_pagina_de_analise_sem_painel_lateral(): void
    {
        $registro = PreRegistration::factory()->create(['status' => 'correcao']);
        $this->withoutVite();

        $this->actingAs($this->operador('pre-registro.analisar'))
            ->get(route('pre-registrations'))
            ->assertOk()
            ->assertSee('Correção')
            ->assertDontSee('class="ui-drawer"', escape: false);

        Livewire::actingAs($this->operador('pre-registro.analisar'))
            ->test(PreRegistrationQueue::class)
            ->call('setStatusFilter', 'correcao')
            ->assertSee(route('pre-registrations.review', $registro->id), escape: false);
    }
}
