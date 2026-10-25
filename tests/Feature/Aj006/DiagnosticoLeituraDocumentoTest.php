<?php

namespace Tests\Feature\Aj006;

use App\Documentos\Dominio\DiagnosticoLeitura;
use App\Documentos\Dominio\FalhaLeituraDocumento;
use App\Livewire\PublicPreRegistration;
use App\Models\Implantacao;
use App\Support\ImplantacaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * AJ-006, fatia 4 — motivos objetivos quando a leitura do documento falha.
 * Respostas do leitor simuladas; nenhuma imagem real de documento.
 */
class DiagnosticoLeituraDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'http://document-ocr:8001/v1/extract';

    protected function setUp(): void
    {
        parent::setUp();

        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        config()->set('services.document_ocr.enabled', true);
        config()->set('services.document_ocr.url', 'http://document-ocr:8001');
        config()->set('services.document_ocr.token', 'token-de-teste');
        RateLimiter::clear('document-ocr:'.hash('sha256', '127.0.0.1'));
    }

    protected function tearDown(): void
    {
        ImplantacaoContext::forgetCurrent();

        parent::tearDown();
    }

    public function test_sem_resposta_do_leitor_e_falha_de_comunicacao_e_preserva_o_preenchimento(): void
    {
        Http::fake([self::URL => fn () => throw new ConnectionException('timeout')]);

        $this->enviarFrente(nome: 'Digitado Antes')
            ->assertSet('ocrStatus', 'failed')
            ->assertSet('ocrDiagnostico.categoria', 'comunicacao')
            ->assertSet('ocrDiagnostico.recapturar', false)
            ->assertSee('O leitor automático não respondeu a tempo.')
            ->assertDontSee('Enviar outra foto')
            ->assertSet('name', 'Digitado Antes');
    }

    public function test_arquivo_recusado_pelo_leitor_pede_outra_foto(): void
    {
        Http::fake([self::URL => Http::response(['detail' => 'invalid_image_content'], 422)]);

        $this->enviarFrente()
            ->assertSet('ocrDiagnostico.categoria', 'captura')
            ->assertSet('ocrDiagnostico.recapturar', true)
            ->assertSee('O arquivo não é uma imagem válida.')
            ->assertSee('Enviar outra foto');
    }

    public function test_leitor_desligado_e_autenticacao_recusada_sao_falhas_de_servico(): void
    {
        Http::fake([self::URL => Http::response(['detail' => 'unauthorized'], 401)]);
        $this->enviarFrente()->assertSet('ocrDiagnostico.categoria', 'servico');

        config()->set('services.document_ocr.enabled', false);
        $this->enviarFrente()
            ->assertSet('ocrStatus', 'unavailable')
            ->assertSet('ocrDiagnostico.categoria', 'servico');
        Http::assertSentCount(1);
    }

    public function test_imagem_ruim_sem_texto_explica_o_que_melhorar(): void
    {
        Http::fake([self::URL => Http::response($this->resposta([], [
            'stage' => 'quality',
            'issues' => ['sem_texto'],
            'quality_issues' => ['desfocada', 'reflexo'],
            'recapture_recommended' => true,
        ]))]);

        $this->enviarFrente()
            ->assertSet('ocrStatus', 'failed')
            ->assertSet('ocrDiagnostico.categoria', 'qualidade')
            ->assertSee('Nenhum texto foi reconhecido.')
            ->assertSee('Imagem desfocada')
            ->assertSee('Há reflexo sobre o documento')
            ->assertSee('Enviar outra foto');
    }

    public function test_texto_sem_campos_e_problema_de_interpretacao_e_sugere_o_verso_do_rg(): void
    {
        Http::fake([self::URL => Http::response($this->resposta([], [
            'stage' => 'interpretation',
            'issues' => ['campos_nao_localizados', 'verso_recomendado'],
            'quality_issues' => [],
            'recapture_recommended' => true,
        ]))]);

        $this->enviarFrente(tipo: 'rg_cin')
            ->assertSet('ocrDiagnostico.categoria', 'interpretacao')
            ->assertSee('os campos não foram localizados')
            ->assertSee('Envie também a foto do verso.');
    }

    public function test_leitura_parcial_sugere_campos_e_avisa_o_que_conferir(): void
    {
        Http::fake([self::URL => Http::response($this->resposta(
            ['name' => ['value' => 'Pessoa Sintetica', 'confidence' => 0.52, 'source' => 'front']],
            ['stage' => 'partial', 'issues' => ['cpf_digito_invalido', 'confianca_baixa'], 'quality_issues' => [], 'recapture_recommended' => false],
        ))]);

        $this->enviarFrente()
            ->assertSet('ocrStatus', 'completed')
            ->assertSet('name', 'Pessoa Sintetica')
            ->assertSet('ocrDiagnostico.categoria', 'parcial')
            ->assertSee('O CPF lido não é válido e não foi sugerido.')
            ->assertSee('Alguns campos foram lidos com pouca confiança.');
    }

    public function test_log_tecnico_registra_so_codigos_sem_dado_pessoal(): void
    {
        Log::spy();
        Http::fake([self::URL => Http::response($this->resposta(
            ['name' => ['value' => 'Pessoa Sintetica', 'confidence' => 0.97, 'source' => 'front'], 'cpf' => ['value' => '123.456.789-09', 'confidence' => 0.98, 'source' => 'front']],
            ['stage' => 'partial', 'issues' => [], 'quality_issues' => ['reflexo'], 'recapture_recommended' => false],
        ))]);

        $this->enviarFrente();

        Log::shouldHaveReceived('info')->withArgs(function (string $mensagem, array $contexto) {
            $serializado = json_encode($contexto);

            return $mensagem === 'leitura_documento'
                && $contexto['categoria'] === 'parcial'
                && $contexto['qualidade'] === ['reflexo']
                && ! str_contains($serializado, 'Sintetica')
                && ! str_contains($serializado, '123.456');
        })->once();
    }

    public function test_diagnostico_classifica_resposta_sem_diagnostico_e_ignora_codigos_desconhecidos(): void
    {
        $antigo = DiagnosticoLeitura::daResposta(['status' => 'completed', 'fields' => ['name' => ['value' => null]]]);
        $this->assertSame('interpretacao', $antigo->categoria);

        $completo = DiagnosticoLeitura::daResposta([
            'fields' => [],
            'diagnostics' => ['stage' => 'ok', 'issues' => ['confianca_baixa', '<script>'], 'quality_issues' => ['inventado'], 'rotation_applied' => ['front' => 90]],
        ]);
        $this->assertSame('validacao', $completo->categoria);
        $this->assertSame(['confianca_baixa'], $completo->codigos);
        $this->assertSame([], $completo->avisosQualidade);
        $this->assertTrue($completo->orientacaoCorrigida);
        $this->assertTrue($completo->concluida());

        $falha = DiagnosticoLeitura::daFalha(new FalhaLeituraDocumento('desconhecida', 'qualquer'));
        $this->assertSame('servico', $falha->categoria);
        $this->assertSame(['erro_interno'], $falha->codigos);
    }

    private function enviarFrente(string $tipo = 'cnh', string $nome = ''): Testable
    {
        return Livewire::test(PublicPreRegistration::class)
            ->set('started', true)
            ->set('step', 3)
            ->set('name', $nome)
            ->set('documentType', $tipo)
            ->set('documentFile', UploadedFile::fake()->image('documento-sintetico.jpg', 900, 600));
    }

    /** @return array<string, mixed> */
    private function resposta(array $campos, array $diagnostico): array
    {
        return [
            'status' => 'completed',
            'engine' => 'rapidocr',
            'model_version' => 'teste',
            'document_type' => 'cnh',
            'fields' => $campos + [
                'name' => ['value' => null], 'cpf' => ['value' => null], 'birth_date' => ['value' => null], 'document_number' => ['value' => null],
            ],
            'portrait' => ['detected' => false],
            'line_count' => 0,
            'diagnostics' => $diagnostico,
        ];
    }
}
