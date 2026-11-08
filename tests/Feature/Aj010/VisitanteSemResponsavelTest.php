<?php

namespace Tests\Feature\Aj010;

use App\Documentos\Dominio\ResultadoOcr;
use App\Documentos\Infra\OcrSimulador;
use App\Livewire\AccessValidation;
use App\Models\AutorizacaoAcesso;
use App\Models\Imovel;
use App\Models\OperacaoIntegracao as Operacao;
use App\Models\Pessoa;
use App\Models\PessoaArquivo;
use App\Models\PessoaContato;
use App\Models\User;
use App\Models\Vinculo;
use App\Services\PrivateFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\Support\ImagensDocumentoSinteticas;
use Tests\Support\ImagensPessoaSinteticas;
use Tests\TestCase;

/**
 * AJ-010 §14 — responsável só para morador. Visitante salva pessoa e foto
 * sem responsável; nenhum responsável, vínculo ou autorização fictícios.
 */
class VisitanteSemResponsavelTest extends TestCase
{
    use MontaCenarioIntegracao, RefreshDatabase;

    private const CPF = '529.982.247-25';

    private User $operador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararIntegracao();
        Storage::fake(PrivateFileService::DISK);
        $this->operador = $this->usuarioCom('validacao.registrar', 'pessoas.gerenciar', 'documentos.capturar', 'facial.enviar');
        Imovel::factory()->create(['codigo' => 'SRA-A-102']);
    }

    protected function tearDown(): void
    {
        $this->encerrarIntegracao();
        parent::tearDown();
    }

    private function foto(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('pessoa.jpg', ImagensPessoaSinteticas::boa());
    }

    private function manual(): Testable
    {
        return Livewire::actingAs($this->operador)->test(AccessValidation::class)
            ->call('openQuickRegistration')
            ->call('digitarManualmente');
    }

    public function test_campo_de_responsavel_aparece_so_para_morador(): void
    {
        $tela = $this->manual()->set('quickAccessType', 'visitante')
            ->assertDontSee('quick-responsible')
            ->assertDontSee('Obrigatório para visitante');

        foreach (['prestador', 'turista', 'outro'] as $tipo) {
            $tela->set('quickAccessType', $tipo)->assertDontSee('quick-responsible');
        }

        $tela->set('quickAccessType', 'morador')
            ->assertSee('quick-responsible', false)
            ->assertSee('Responsável (opcional)');
    }

    /** @return array<string, array{string, string}> */
    public static function combinacoes(): array
    {
        return [
            'com telefone e imóvel' => ['(12) 99999-1234', 'SRA-A-102'],
            'sem telefone' => ['', 'SRA-A-102'],
            'sem imóvel' => ['(12) 99999-1234', ''],
            'sem telefone e sem imóvel' => ['', ''],
        ];
    }

    #[DataProvider('combinacoes')]
    public function test_visitante_manual_salva_pessoa_e_foto_sem_responsavel(string $telefone, string $imovel): void
    {
        $this->manual()
            ->set('quickName', 'Visitante Sem Responsavel')
            ->set('quickDocument', self::CPF)
            ->set('quickPhone', $telefone)
            ->set('quickAccessType', 'visitante')
            ->set('quickPropertyCode', $imovel)
            ->call('irParaEtapa', 'foto')
            ->assertHasNoErrors()
            ->set('fotoPessoa', $this->foto())
            ->call('saveQuickRegistration')
            ->assertHasNoErrors()
            ->assertSet('quickResultado.cadastro.title', 'Cadastro salvo')
            ->assertSet('quickResultado.foto.title', 'Foto salva');

        $this->assertSame(1, Pessoa::query()->count());
        $this->assertSame(1, PessoaArquivo::query()->where('is_current', true)->count());
        $this->assertSame($telefone === '' ? 0 : 1, PessoaContato::query()->count());
        $this->assertSame($imovel === '' ? 0 : 1, Vinculo::query()->count());
        $this->assertSame(0, AutorizacaoAcesso::query()->count());
    }

    public function test_visitante_apos_ocr_salva_sem_responsavel(): void
    {
        app(OcrSimulador::class)->responderCom(new ResultadoOcr(true, [
            'nome' => ['valor' => 'Visitante Lido', 'confianca' => 0.99],
            'cpf' => ['valor' => self::CPF, 'confianca' => 0.99],
            'data_nascimento' => ['valor' => '1990-05-12', 'confianca' => 0.99],
        ]));

        Livewire::actingAs($this->operador)->test(AccessValidation::class)
            ->call('openQuickRegistration')
            ->set('capturaFrente', UploadedFile::fake()->createWithContent('frente.jpg', ImagensDocumentoSinteticas::boa()))
            ->assertSet('quickEtapa', 'conferencia')
            ->set('quickAccessType', 'visitante')
            ->call('irParaEtapa', 'foto')
            ->assertHasNoErrors()
            ->set('fotoPessoa', $this->foto())
            ->call('saveQuickRegistration')
            ->assertHasNoErrors()
            ->assertSet('quickPersonRegistered', true);

        $this->assertSame('Visitante Lido', Pessoa::query()->sole()->nome);
        $this->assertSame(1, PessoaArquivo::query()->count());
    }

    public function test_troca_de_morador_para_visitante_limpa_o_responsavel_e_valor_forjado_e_ignorado(): void
    {
        $tela = $this->manual()
            ->set('quickName', 'Pessoa Trocada')
            ->set('quickDocument', self::CPF)
            ->set('quickAccessType', 'morador')
            ->set('quickResponsible', 'Responsável Digitado')
            ->set('quickAccessType', 'visitante')
            ->assertSet('quickResponsible', '');

        // Valor enviado direto para visitante (sem o campo na tela) não é usado.
        $tela->set('quickResponsible', 'Responsável Forjado')
            ->set('quickPropertyCode', 'SRA-A-102')
            ->set('quickTerminalId', $this->terminalAtivo(['nome' => 'Facial entrada'])->id)
            ->set('quickTerminalConfirmado', true)
            ->call('saveQuickRegistrationAndSend')
            ->assertHasNoErrors()
            ->assertSet('quickResponsible', '')
            ->assertSet('quickPersonRegistered', true);

        $this->assertStringNotContainsString('Forjado', json_encode(DB::table('autorizacoes_acesso')->get()));
        $this->assertStringNotContainsString('Forjado', json_encode(DB::table('vinculos')->get()));
        $this->assertSame(['visitante'], Vinculo::query()->pluck('tipo')->all());
    }

    public function test_morador_mantem_o_responsavel_opcional(): void
    {
        $this->manual()
            ->set('quickName', 'Morador Sem Responsavel')
            ->set('quickDocument', self::CPF)
            ->set('quickAccessType', 'morador')
            ->set('quickPropertyCode', 'SRA-A-102')
            ->call('saveQuickRegistration')
            ->assertHasNoErrors()
            ->assertSet('quickPersonRegistered', true);

        $this->manual()
            ->set('quickName', 'Morador Com Responsavel')
            ->set('quickDocument', '111.444.777-35')
            ->set('quickAccessType', 'morador')
            ->set('quickResponsible', str_repeat('x', 121))
            ->set('quickPropertyCode', 'SRA-A-102')
            ->call('saveQuickRegistration')
            ->assertHasErrors('quickResponsible');
    }

    public function test_demais_tipos_salvam_sem_responsavel(): void
    {
        foreach (['prestador' => '111.444.777-35', 'turista' => '935.411.347-80', 'outro' => '286.255.878-87'] as $tipo => $cpf) {
            $this->manual()
                ->set('quickName', 'Pessoa '.$tipo)
                ->set('quickDocument', $cpf)
                ->set('quickAccessType', $tipo)
                ->call('saveQuickRegistration')
                ->assertHasNoErrors()
                ->assertSet('quickPersonRegistered', true);
        }
        $this->assertSame(3, Pessoa::query()->count());
        $this->assertSame(0, AutorizacaoAcesso::query()->count());
    }

    public function test_sem_autorizacao_vigente_o_envio_continua_impedido(): void
    {
        $terminal = $this->terminalAtivo(['nome' => 'Facial entrada']);
        $this->usarFilaReal();

        $this->manual()
            ->set('quickName', 'Visitante Sem Imovel')
            ->set('quickDocument', self::CPF)
            ->set('quickAccessType', 'visitante')
            ->call('irParaEtapa', 'foto')
            ->set('fotoPessoa', $this->foto())
            ->set('quickTerminalId', $terminal->id)
            ->set('quickTerminalConfirmado', true)
            ->call('saveQuickRegistrationAndSend')
            ->assertSet('quickResultado.cadastro.title', 'Cadastro salvo')
            ->assertSet('quickResultado.foto.title', 'Foto salva')
            ->assertSet('quickResultado.facial.variant', 'danger');

        $this->assertSame(0, AutorizacaoAcesso::query()->count());
        $this->assertSame(0, Operacao::query()->where('operacao', 'sincronizar_credencial')->count());
        $this->assertSame(0, $this->simulador()->totalAberturasFisicas($terminal->id));
    }
}
