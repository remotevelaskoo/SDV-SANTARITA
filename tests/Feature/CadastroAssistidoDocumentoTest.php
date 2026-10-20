<?php

namespace Tests\Feature;

use App\Documentos\Aplicacao\CadastroAssistidoDocumento;
use App\Documentos\Dominio\PortaOcrDocumento;
use App\Documentos\Dominio\ResultadoOcr;
use App\Documentos\Infra\AvaliadorQualidadeImagem;
use App\Documentos\Infra\OcrServicoDocumento;
use App\Documentos\Infra\OcrSimulador;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Livewire\AccessValidation;
use App\Models\AuditoriaEvento;
use App\Models\HistoricoAcesso;
use App\Models\Imovel;
use App\Models\Implantacao;
use App\Models\Perfil;
use App\Models\Permissao;
use App\Models\Pessoa;
use App\Models\PessoaDocumento;
use App\Models\User;
use App\Models\UsuarioPerfil;
use App\Models\Vinculo;
use App\Support\ImplantacaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\ImagensDocumentoSinteticas;
use Tests\TestCase;

/**
 * Cadastro rápido assistido por câmera (SDV-OPE-018 §5, §7 e §8).
 */
class CadastroAssistidoDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private const CPF = '529.982.247-25';

    protected function setUp(): void
    {
        parent::setUp();
        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        Imovel::factory()->create(['codigo' => 'SRA-A-102']);
    }

    protected function tearDown(): void
    {
        ImplantacaoContext::forgetCurrent();
        parent::tearDown();
    }

    private function operador(bool $comCaptura = true): User
    {
        $usuario = User::factory()->create();
        $perfil = Perfil::factory()->create();
        foreach (array_filter(['validacao.registrar', $comCaptura ? CadastroAssistidoDocumento::PERMISSAO : null]) as $chave) {
            $permissao = Permissao::query()->firstOrCreate(['chave' => $chave], ['modulo' => 'teste', 'descricao' => $chave]);
            $perfil->permissoes()->attach($permissao->id, ['id' => (string) Str::uuid7(), 'implantacao_id' => ImplantacaoContext::current()->id]);
        }
        UsuarioPerfil::factory()->for($usuario)->for($perfil)->create();

        return $usuario;
    }

    private function ocrResponde(array $campos): void
    {
        app(OcrSimulador::class)->responderCom(new ResultadoOcr(true, $campos));
    }

    private function arquivo(string $conteudo, string $nome = 'frente.jpg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nome, $conteudo);
    }

    // ---- Qualidade -------------------------------------------------------------

    public function test_avaliador_detecta_problemas_e_aprova_imagem_boa(): void
    {
        $avaliador = app(AvaliadorQualidadeImagem::class);

        $this->assertTrue($avaliador->avaliar(ImagensDocumentoSinteticas::boa())['aprovada']);
        $casos = [
            'desfocada' => ImagensDocumentoSinteticas::desfocada(),
            'cortado' => ImagensDocumentoSinteticas::cortada(),
            'escura' => ImagensDocumentoSinteticas::escura(),
            'reflexo' => ImagensDocumentoSinteticas::comReflexo(),
            'resolucao_baixa' => ImagensDocumentoSinteticas::pequena(),
            'nao_identificado' => ImagensDocumentoSinteticas::vazia(),
        ];
        foreach ($casos as $codigo => $imagem) {
            $this->assertArrayHasKey($codigo, $avaliador->avaliar($imagem)['problemas'], $codigo);
        }
    }

    public function test_captura_ruim_pede_repeticao_e_nao_preenche_nada(): void
    {
        $this->ocrResponde(['nome' => ['valor' => 'Pessoa Sintética', 'confianca' => 0.99]]);

        Livewire::actingAs($this->operador())->test(AccessValidation::class)
            ->call('openQuickRegistration')
            ->set('capturaFrente', $this->arquivo(ImagensDocumentoSinteticas::desfocada()))
            ->call('analisarDocumento')
            ->assertSet('analiseDocumento.status', 'repetir')
            ->assertSet('quickName', '')
            ->assertSee('Imagem desfocada. Aproxime o documento e capture novamente.');
    }

    // ---- OCR e conferência --------------------------------------------------------

    public function test_baixa_confianca_exige_conferencia_e_correcao_manual_funciona(): void
    {
        $this->ocrResponde([
            'nome' => ['valor' => 'Pessoa Sintetica', 'confianca' => 0.62],
            'cpf' => ['valor' => self::CPF, 'confianca' => 0.97],
            'data_nascimento' => ['valor' => '1990-05-12', 'confianca' => 0.91],
            'numero_documento' => ['valor' => 'RG-SINTETICO', 'confianca' => 0.5],
        ]);

        $tela = Livewire::actingAs($this->operador())->test(AccessValidation::class)
            ->call('openQuickRegistration')
            ->set('capturaFrente', $this->arquivo(ImagensDocumentoSinteticas::boa()))
            ->set('capturaVerso', $this->arquivo(ImagensDocumentoSinteticas::boa(), 'verso.jpg'))
            ->call('analisarDocumento')
            ->assertSet('analiseDocumento.status', 'sugestao')
            ->assertSet('quickName', 'Pessoa Sintetica')
            ->assertSet('quickDocument', self::CPF)
            ->assertSet('quickBirthDate', '1990-05-12')
            ->assertSet('camposConferencia', ['quickName'])
            ->assertSee('Requer conferência')
            ->set('quickPhone', '(12) 99999-1234')
            ->set('quickResponsible', 'Responsável')
            ->set('quickPropertyCode', 'SRA-A-102')
            ->call('saveQuickRegistration')
            ->assertHasErrors('quickName');
        $this->assertSame(0, Pessoa::query()->count(), 'Não salva sem conferência.');

        // O operador corrige o nome: isso conta como conferência.
        $tela->set('quickName', 'Pessoa Sintética Corrigida')
            ->assertSet('camposConferencia', [])
            ->call('saveQuickRegistration')
            ->assertHasNoErrors();

        $pessoa = Pessoa::query()->sole();
        $this->assertSame('Pessoa Sintética Corrigida', $pessoa->nome);
        $this->assertSame('1990-05-12', $pessoa->data_nascimento?->format('Y-m-d') ?? (string) $pessoa->data_nascimento);
    }

    public function test_documento_nao_identificado_permite_digitacao_manual(): void
    {
        Livewire::actingAs($this->operador())->test(AccessValidation::class)
            ->call('openQuickRegistration')
            ->set('capturaFrente', $this->arquivo(ImagensDocumentoSinteticas::boa()))
            ->call('analisarDocumento')
            ->assertSet('analiseDocumento.status', 'nao_reconhecido')
            ->assertSee('Documento não identificado. Faça nova captura ou digite os dados.')
            ->call('digitarManualmente')
            ->assertSet('analiseDocumento', null)
            ->set('quickName', 'Digitado Manualmente')
            ->assertSet('quickName', 'Digitado Manualmente');
    }

    public function test_contingencia_por_arquivo_e_duplicidade(): void
    {
        $existente = Pessoa::factory()->create();
        PessoaDocumento::factory()->for($existente, 'pessoa')->create([
            'tipo' => 'cpf', 'valor_normalizado' => preg_replace('/\D/', '', self::CPF), 'valor_apresentacao' => self::CPF,
        ]);
        $this->ocrResponde(['nome' => ['valor' => 'Pessoa Sintetica', 'confianca' => 0.99], 'cpf' => ['valor' => self::CPF, 'confianca' => 0.99]]);
        $png = $this->arquivo($this->comoPng(ImagensDocumentoSinteticas::boa()), 'frente.png');

        Livewire::actingAs($this->operador())->test(AccessValidation::class)
            ->call('openQuickRegistration')
            ->set('capturaOrigem', 'arquivo')
            ->set('capturaFrente', $png)
            ->call('analisarDocumento')
            ->assertSet('analiseDocumento.status', 'sugestao')
            ->assertSet('analiseDocumento.duplicidade', true)
            ->assertSet('quickDuplicateFound', true)
            ->assertSee('Possível duplicidade');

        $this->assertSame('arquivo', AuditoriaEvento::query()->where('action', 'documento_capturado_analisado')->with('context')->sole()->context->metadata['origem']);
    }

    // ---- Permissão e sigilo -----------------------------------------------------

    public function test_usuario_sem_permissao_nao_ve_nem_usa_a_captura(): void
    {
        $semPermissao = $this->operador(comCaptura: false);

        Livewire::actingAs($semPermissao)->test(AccessValidation::class)
            ->call('openQuickRegistration')
            ->assertDontSee('Capturar documento')
            ->set('capturaFrente', $this->arquivo(ImagensDocumentoSinteticas::boa()))
            ->call('analisarDocumento')
            ->assertHasErrors('capturaFrente')
            ->assertSet('analiseDocumento', null);

        $this->expectException(RegraIntegracaoViolada::class);
        app(CadastroAssistidoDocumento::class)->analisar($semPermissao, ImagensDocumentoSinteticas::boa());
    }

    public function test_nenhuma_imagem_ou_dado_sensivel_em_log_auditoria_ou_disco(): void
    {
        Log::spy();
        $this->ocrResponde(['nome' => ['valor' => 'Nome Sigiloso', 'confianca' => 0.99], 'cpf' => ['valor' => self::CPF, 'confianca' => 0.99]]);
        $frente = $this->arquivo(ImagensDocumentoSinteticas::boa());

        $tela = Livewire::actingAs($this->operador())->test(AccessValidation::class)
            ->call('openQuickRegistration')
            ->set('capturaFrente', $frente)
            ->call('analisarDocumento');

        $auditoria = json_encode([DB::table('auditoria_eventos')->get(), DB::table('auditoria_contextos')->get(), DB::table('auditoria_alteracoes')->get()]);
        $this->assertStringNotContainsString('Nome Sigiloso', $auditoria);
        $this->assertStringNotContainsString(preg_replace('/\D/', '', self::CPF), $auditoria);
        $this->assertStringNotContainsString('/9j/', $auditoria, 'Nenhuma imagem em base64.');
        $this->assertNull($tela->get('capturaFrente'), 'O arquivo temporário é descartado após a análise.');
        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('warning');
    }

    public function test_ocr_nunca_gera_autorizacao_ou_liberacao(): void
    {
        $this->ocrResponde(['nome' => ['valor' => 'Pessoa Sintetica', 'confianca' => 0.99], 'cpf' => ['valor' => self::CPF, 'confianca' => 0.99]]);

        app(CadastroAssistidoDocumento::class)->analisar($this->operador(), ImagensDocumentoSinteticas::boa());

        $this->assertSame(0, Pessoa::query()->count());
        $this->assertSame(0, Vinculo::query()->count());
        $this->assertSame(0, HistoricoAcesso::query()->count());
        $this->assertSame(0, DB::table('operacoes_integracao')->count());
    }

    // ---- Leitor interno do AJ-005 ----------------------------------------------

    private function usarLeitorInterno(): void
    {
        config(['services.document_ocr.enabled' => true, 'services.document_ocr.url' => 'http://ocr.interno.test', 'services.document_ocr.token' => 'token-de-teste']);
        $this->app->instance(PortaOcrDocumento::class, app(OcrServicoDocumento::class));
    }

    public function test_leitor_interno_preenche_sugestoes_com_a_confianca_do_servico(): void
    {
        $this->usarLeitorInterno();
        Http::fake(['ocr.interno.test/v1/extract' => Http::response([
            'status' => 'completed',
            'fields' => [
                'name' => ['value' => 'Pessoa Sintetica', 'confidence' => 0.97, 'source' => 'front'],
                'cpf' => ['value' => self::CPF, 'confidence' => 0.6, 'source' => 'front'],
                'birth_date' => ['value' => '1990-05-12', 'confidence' => null, 'source' => null],
                'document_number' => ['value' => null, 'confidence' => null, 'source' => null],
            ],
            'portrait' => ['detected' => false],
        ])]);

        Livewire::actingAs($this->operador())->test(AccessValidation::class)
            ->call('openQuickRegistration')
            ->set('capturaTipoDocumento', 'rg_cin')
            ->set('capturaFrente', $this->arquivo(ImagensDocumentoSinteticas::boa()))
            ->call('analisarDocumento')
            ->assertSet('analiseDocumento.status', 'sugestao')
            ->assertSet('quickName', 'Pessoa Sintetica')
            ->assertSet('quickDocument', self::CPF)
            ->assertSet('quickBirthDate', '1990-05-12')
            ->assertSet('camposConferencia', ['quickDocument', 'quickBirthDate']);

        Http::assertSent(fn (Request $r) => $r->url() === 'http://ocr.interno.test/v1/extract'
            && $r->hasHeader('Authorization', 'Bearer token-de-teste')
            && collect($r->data())->contains(fn ($parte) => ($parte['name'] ?? null) === 'document_type' && $parte['contents'] === 'rg_cin')
            && collect($r->data())->contains(fn ($parte) => ($parte['name'] ?? null) === 'include_portrait' && $parte['contents'] === 'false'));
    }

    public function test_leitor_interno_fora_do_ar_permite_digitacao_manual(): void
    {
        $this->usarLeitorInterno();
        Http::fake(['ocr.interno.test/*' => Http::response([], 503)]);

        Livewire::actingAs($this->operador())->test(AccessValidation::class)
            ->call('openQuickRegistration')
            ->set('capturaFrente', $this->arquivo(ImagensDocumentoSinteticas::boa()))
            ->call('analisarDocumento')
            ->assertSet('analiseDocumento.status', 'nao_reconhecido')
            ->assertSee('A leitura automática não está disponível agora. Faça nova captura ou digite os dados.')
            ->assertSet('quickName', '');
    }

    public function test_imagem_ruim_nem_chega_ao_leitor_interno(): void
    {
        $this->usarLeitorInterno();
        Http::fake();

        app(CadastroAssistidoDocumento::class)->analisar($this->operador(), ImagensDocumentoSinteticas::escura());

        Http::assertNothingSent();
    }

    public function test_tipo_de_documento_invalido_e_recusado(): void
    {
        $this->expectException(RegraIntegracaoViolada::class);
        app(CadastroAssistidoDocumento::class)->analisar($this->operador(), ImagensDocumentoSinteticas::boa(), tipoDocumento: 'passaporte');
    }

    private function comoPng(string $jpeg): string
    {
        ob_start();
        imagepng(imagecreatefromstring($jpeg));

        return (string) ob_get_clean();
    }
}
