<?php

namespace Tests\Feature;

use App\Livewire\PublicPreRegistration;
use App\Models\Implantacao;
use App\Support\ImplantacaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentOcrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        config()->set('services.document_ocr.enabled', true);
        config()->set('services.document_ocr.url', 'http://document-ocr:8001');
        config()->set('services.document_ocr.token', 'internal-test-token');
        RateLimiter::clear('document-ocr:'.hash('sha256', '127.0.0.1'));
    }

    protected function tearDown(): void
    {
        ImplantacaoContext::forgetCurrent();

        parent::tearDown();
    }

    public function test_document_reader_suggests_fields_for_human_review(): void
    {
        Http::fake([
            'http://document-ocr:8001/v1/extract' => Http::response($this->successfulResult()),
        ]);

        Livewire::test(PublicPreRegistration::class)
            ->set('started', true)
            ->set('step', 3)
            ->set('documentType', 'cnh')
            ->set('documentFile', UploadedFile::fake()->image('cnh.jpg', 900, 600))
            ->assertSet('ocrStatus', 'completed')
            ->assertSet('name', 'Maria Teste da Silva')
            ->assertSet('cpf', '123.456.789-09')
            ->assertSet('birthDate', '1992-05-15')
            ->assertSet('extractedDocumentNumber', '01234567890')
            ->assertSet('portraitDetected', true)
            ->assertSee('Sugestões encontradas')
            ->assertSee('Foto localizada no documento')
            ->assertSee('Conferir dados pessoais');

        Http::assertSent(fn ($request) => $request->url() === 'http://document-ocr:8001/v1/extract'
            && $request->hasHeader('Authorization', 'Bearer internal-test-token'));
    }

    public function test_confirmed_suggestions_are_saved_separately_from_the_registration(): void
    {
        Storage::fake('private-files');
        Http::fake([
            'http://document-ocr:8001/v1/extract' => Http::response($this->successfulResult()),
        ]);

        Livewire::test(PublicPreRegistration::class)
            ->set('name', 'Nome digitado antes da leitura')
            ->set('cpf', '123.456.789-09')
            ->set('birthDate', '1992-05-15')
            ->set('phone', '(12) 99999-9999')
            ->set('email', 'maria@example.com')
            ->set('zipCode', '12000-000')
            ->set('address', 'Rua das Flores')
            ->set('addressNumber', '120')
            ->set('district', 'Centro')
            ->set('city', 'Taubaté')
            ->set('state', 'SP')
            ->set('documentFile', UploadedFile::fake()->image('cnh.jpg', 900, 600))
            ->set('selfieFile', UploadedFile::fake()->image('selfie.jpg', 600, 900))
            ->set('privacyAccepted', true)
            ->call('submit')
            ->assertSet('submitted', true);

        $this->assertDatabaseHas('pre_registrations', [
            'name' => 'Maria Teste da Silva',
            'document' => '123.456.789-09',
        ]);
        $this->assertDatabaseHas('pre_registration_document_extractions', [
            'engine' => 'rapidocr',
            'model_version' => 'PP-OCRv6-small',
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('pre_registration_arquivos', [
            'category' => 'documento_foto',
            'is_current' => true,
        ]);
        $this->assertDatabaseHas('arquivos', [
            'display_name' => 'Foto localizada no documento',
            'classification' => 'sensivel',
            'detected_mime' => 'image/jpeg',
        ]);
    }

    public function test_public_reader_limits_repeated_attempts_and_preserves_manual_entry(): void
    {
        $key = 'document-ocr:'.hash('sha256', '127.0.0.1');
        foreach (range(1, 5) as $_attempt) {
            RateLimiter::hit($key, 60);
        }
        Http::fake();

        Livewire::test(PublicPreRegistration::class)
            ->set('started', true)
            ->set('step', 3)
            ->set('documentFile', UploadedFile::fake()->image('cnh.jpg', 900, 600))
            ->assertSet('ocrStatus', 'failed')
            ->assertSee('muitas tentativas')
            ->set('name', 'Preenchimento Manual')
            ->assertSet('name', 'Preenchimento Manual');

        Http::assertNothingSent();
    }

    /** @return array<string, mixed> */
    private function successfulResult(): array
    {
        $portrait = UploadedFile::fake()->image('portrait.jpg', 180, 240);
        $portraitContents = file_get_contents($portrait->getRealPath());

        return [
            'status' => 'completed',
            'engine' => 'rapidocr',
            'model_version' => 'PP-OCRv6-small',
            'document_type' => 'cnh',
            'fields' => [
                'name' => ['value' => 'Maria Teste da Silva', 'confidence' => 0.99, 'source' => 'front'],
                'cpf' => ['value' => '123.456.789-09', 'confidence' => 0.98, 'source' => 'front'],
                'birth_date' => ['value' => '1992-05-15', 'confidence' => 0.97, 'source' => 'front'],
                'document_number' => ['value' => '01234567890', 'confidence' => 0.96, 'source' => 'front'],
            ],
            'portrait' => [
                'detected' => true,
                'confidence' => null,
                'source' => 'front',
                'image_base64' => base64_encode((string) $portraitContents),
            ],
        ];
    }
}
