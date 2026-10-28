<?php

namespace Tests\Feature;

use App\Livewire\PreRegistrationReview;
use App\Livewire\PublicPreRegistration;
use App\Models\Implantacao;
use App\Models\PreRegistration;
use App\Services\PrivateFileService;
use App\Support\ImplantacaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\OperadorComPermissoes;
use Tests\TestCase;

/**
 * Jornada documental do pré-cadastro (SDV-UXP-006 §11, SDV-UXP-019 §12,
 * SDV-OPE-020 §12): frente obrigatória, verso quando aplicável, selfie
 * separada e OCR apenas assistivo. Só dados e imagens sintéticos.
 */
class PreCadastroDocumentoObrigatorioTest extends TestCase
{
    use OperadorComPermissoes, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        Storage::fake(PrivateFileService::DISK);
        RateLimiter::clear('document-ocr:'.hash('sha256', '127.0.0.1'));
    }

    protected function tearDown(): void
    {
        ImplantacaoContext::forgetCurrent();
        parent::tearDown();
    }

    public function test_etapa_do_documento_nao_pode_ser_desligada_por_configuracao(): void
    {
        $this->assertNull(config('documentos.pre_cadastro'));

        Livewire::test(PublicPreRegistration::class)
            ->call('start')
            ->assertSee('Etapa 1 de 6');

        $this->ateEtapaDoDocumento()
            ->assertSet('step', 3)
            ->assertSee('Etapa 3 de 6')
            ->assertSee('Fotografe ou envie seu documento');
    }

    public function test_nao_avanca_nem_envia_sem_a_frente_do_documento(): void
    {
        $this->ateEtapaDoDocumento()
            ->call('nextStep')
            ->assertSet('step', 3)
            ->assertHasErrors(['documentFile' => 'required']);

        $this->comDadosCompletos()
            ->set('documentFile', null)
            ->call('submit')
            ->assertHasErrors(['documentFile' => 'required'])
            ->assertSet('submitted', false);

        $this->assertDatabaseCount('pre_registrations', 0);
        $this->assertDatabaseCount('arquivos', 0);
    }

    public function test_verso_e_exigido_no_rg_cin_e_opcional_na_cnh(): void
    {
        $this->ateEtapaDoDocumento()
            ->set('documentType', 'rg_cin')
            ->assertSee('(obrigatório no RG/CIN)')
            ->set('documentFile', $this->imagem('frente.jpg'))
            ->call('nextStep')
            ->assertSet('step', 3)
            ->assertHasErrors(['documentBackFile' => 'required'])
            ->assertSee('Envie uma foto do verso do RG/CIN para continuar.')
            ->set('documentBackFile', $this->imagem('verso.jpg'))
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('step', 4);

        $this->comDadosCompletos()
            ->set('documentType', 'rg_cin')
            ->call('submit')
            ->assertHasErrors(['documentBackFile' => 'required'])
            ->assertSet('submitted', false);

        $this->ateEtapaDoDocumento()
            ->set('documentType', 'cnh')
            ->assertSee('(quando houver)')
            ->set('documentFile', $this->imagem('frente.jpg'))
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('step', 4);
    }

    public function test_selfie_continua_obrigatoria_e_armazenada_separada_do_documento(): void
    {
        $this->comDadosCompletos()
            ->set('selfieFile', null)
            ->call('submit')
            ->assertHasErrors(['selfieFile' => 'required']);

        $this->comDadosCompletos()
            ->set('documentType', 'rg_cin')
            ->set('documentBackFile', $this->imagem('verso.jpg'))
            ->call('submit')
            ->assertSet('submitted', true);

        $registro = PreRegistration::query()->sole();
        $this->assertSame('rg_cin', $registro->document_type);

        $frente = $registro->currentFileLink('documento')?->file;
        $verso = $registro->currentFileLink('documento_verso')?->file;
        $selfie = $registro->currentFileLink('selfie')?->file;
        $this->assertNotNull($frente);
        $this->assertNotNull($verso);
        $this->assertNotNull($selfie);
        $this->assertCount(3, array_unique([$frente->id, $verso->id, $selfie->id]));
        $this->assertDatabaseCount('arquivos', 3);
    }

    public function test_ocr_indisponivel_nao_impede_o_envio_mas_exige_conferencia_manual(): void
    {
        config()->set('services.document_ocr.enabled', false);

        $this->comDadosCompletos()
            ->assertSet('ocrStatus', 'unavailable')
            ->call('submit')
            ->assertSet('submitted', true);

        $registro = PreRegistration::query()->sole();
        $this->assertSame('aguardando', $registro->status);
        $this->assertStringContainsString('conferência manual obrigatória', $registro->document_status);
        $this->assertDatabaseCount('pre_registration_document_extractions', 0);
        $this->assertNotNull($registro->currentFileLink('documento')?->file);

        Livewire::actingAs($this->operador('pre-registro.analisar'))
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->assertSee('Conferência manual obrigatória')
            ->assertSee('A leitura automática não foi realizada');
    }

    public function test_falha_do_servico_de_ocr_mantem_os_dados_e_permite_o_envio(): void
    {
        $this->ligarOcr();
        Http::fake(['http://document-ocr:8001/v1/extract' => Http::response(['detail' => 'erro'], 500)]);

        $this->comDadosCompletos()
            ->assertSet('ocrStatus', 'failed')
            ->assertSet('name', 'Pessoa Ficticia de Teste')
            ->call('submit')
            ->assertSet('submitted', true);

        $registro = PreRegistration::query()->sole();
        $this->assertSame('aguardando', $registro->status);
        $this->assertStringContainsString('leitura automática não realizada', $registro->document_status);
    }

    public function test_baixa_confianca_do_ocr_exige_revisao_manual_e_nao_aprova(): void
    {
        $this->ligarOcr();
        Http::fake(['http://document-ocr:8001/v1/extract' => Http::response($this->leituraSintetica(confianca: 0.4))]);

        $this->comDadosCompletos()
            ->assertSet('ocrStatus', 'completed')
            ->call('submit')
            ->assertSet('submitted', true);

        $registro = PreRegistration::query()->sole();
        $this->assertSame('aguardando', $registro->status);
        $this->assertStringContainsString('baixa confiança — conferência manual obrigatória', $registro->document_status);

        Livewire::actingAs($this->operador('pre-registro.analisar'))
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->assertSee('campos para conferir');
    }

    public function test_ocr_com_alta_confianca_nunca_aprova_nem_libera_acesso(): void
    {
        $this->ligarOcr();
        Http::fake(['http://document-ocr:8001/v1/extract' => Http::response($this->leituraSintetica(confianca: 0.99))]);

        $this->comDadosCompletos()
            ->call('submit')
            ->assertSet('submitted', true)
            ->assertSee('O protocolo não é uma autorização');

        $registro = PreRegistration::query()->sole();
        $this->assertSame('aguardando', $registro->status);
        $this->assertNull($registro->status_changed_at);
        $this->assertSame('Arquivo protegido disponível para conferência', $registro->document_status);
        $this->assertDatabaseCount('historico_acessos', 0);
        $this->assertDatabaseCount('autorizacoes_acesso', 0);
        $this->assertDatabaseCount('credenciais_acesso', 0);
        $this->assertDatabaseCount('equipamento_credenciais', 0);
    }

    private function ateEtapaDoDocumento(): Testable
    {
        return Livewire::test(PublicPreRegistration::class)
            ->call('start')
            ->set('name', 'Pessoa Ficticia de Teste')
            ->set('cpf', '000.000.001-91')
            ->set('birthDate', '1990-01-01')
            ->set('phone', '(12) 90000-0000')
            ->set('email', 'pessoa.ficticia@example.com')
            ->call('nextStep')
            ->set('zipCode', '00000-000')
            ->set('address', 'Rua Ficticia')
            ->set('addressNumber', '1')
            ->set('district', 'Bairro Teste')
            ->set('city', 'Cidade Teste')
            ->set('state', 'SP')
            ->call('nextStep');
    }

    private function comDadosCompletos(): Testable
    {
        return $this->ateEtapaDoDocumento()
            ->set('documentType', 'cnh')
            ->set('documentFile', $this->imagem('frente.jpg'))
            ->set('selfieFile', $this->imagem('selfie.jpg', 480, 640))
            ->set('privacyAccepted', true);
    }

    private function imagem(string $nome, int $largura = 900, int $altura = 600): UploadedFile
    {
        return UploadedFile::fake()->image($nome, $largura, $altura);
    }

    private function ligarOcr(): void
    {
        config()->set('services.document_ocr.enabled', true);
        config()->set('services.document_ocr.url', 'http://document-ocr:8001');
        config()->set('services.document_ocr.token', 'internal-test-token');
    }

    /** @return array<string, mixed> */
    private function leituraSintetica(float $confianca): array
    {
        return [
            'status' => 'completed',
            'engine' => 'rapidocr',
            'model_version' => 'teste-sintetico',
            'document_type' => 'cnh',
            'fields' => [
                'name' => ['value' => 'Pessoa Ficticia de Teste', 'confidence' => $confianca, 'source' => 'front'],
                'cpf' => ['value' => '000.000.001-91', 'confidence' => $confianca, 'source' => 'front'],
                'birth_date' => ['value' => '1990-01-01', 'confidence' => $confianca, 'source' => 'front'],
            ],
            'portrait' => ['detected' => false],
            'warnings' => [],
        ];
    }
}
