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
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\Support\ImagensDocumentoSinteticas;
use Tests\Support\ImagensPessoaSinteticas;
use Tests\TestCase;

/**
 * AJ-010 §14 (revisão do PO em 08/11/2026): turista entra por até 24 h sem
 * imóvel nem responsável; visitante fica atrelado a um imóvel e a um
 * responsável; morador tem responsável opcional; demais tipos sem responsável.
 */
class ResponsavelEImovelPorTipoTest extends TestCase
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

    private function enviarAoTerminal(Testable $tela): Testable
    {
        return $tela->set('quickTerminalId', $this->terminalAtivo(['nome' => 'Facial entrada'])->id)
            ->set('quickTerminalConfirmado', true)
            ->call('saveQuickRegistrationAndSend');
    }

    public function test_campos_por_tipo_na_tela(): void
    {
        $tela = $this->manual()->set('quickAccessType', 'visitante')
            ->assertSee('Responsável pelo visitante')
            ->assertSee('Imóvel que o visitante vai visitar.');

        $tela->set('quickAccessType', 'turista')
            ->assertDontSee('quick-responsible')
            ->assertSee('Código do imóvel (opcional)');

        foreach (['prestador', 'outro'] as $tipo) {
            $tela->set('quickAccessType', $tipo)->assertDontSee('quick-responsible');
        }

        $tela->set('quickAccessType', 'morador')->assertSee('Responsável (opcional)');
    }

    public function test_visitante_sem_imovel_ou_sem_responsavel_nao_avanca_nem_salva(): void
    {
        $this->manual()
            ->set('quickName', 'Visitante Incompleto')
            ->set('quickDocument', self::CPF)
            ->set('quickAccessType', 'visitante')
            ->call('irParaEtapa', 'foto')
            ->assertHasErrors(['quickPropertyCode', 'quickResponsible'])
            ->assertSet('quickEtapa', 'conferencia')
            ->call('saveQuickRegistration')
            ->assertHasErrors(['quickPropertyCode', 'quickResponsible'])
            ->assertSet('quickPersonRegistered', false);

        $this->assertSame(0, Pessoa::query()->count());
    }

    public function test_visitante_salva_com_imovel_e_responsavel_e_telefone_opcional(): void
    {
        $this->manual()
            ->set('quickName', 'Visitante Completo')
            ->set('quickDocument', self::CPF)
            ->set('quickAccessType', 'visitante')
            ->set('quickResponsible', 'Morador Anfitrião')
            ->set('quickPropertyCode', 'SRA-A-102')
            ->call('irParaEtapa', 'foto')
            ->assertHasNoErrors()
            ->set('fotoPessoa', $this->foto())
            ->call('saveQuickRegistration')
            ->assertHasNoErrors()
            ->assertSet('quickResultado.foto.title', 'Foto salva');

        $this->assertSame(1, PessoaArquivo::query()->count());
        $this->assertSame(0, PessoaContato::query()->count());
        $this->assertSame(['visitante'], Vinculo::query()->pluck('tipo')->all());
        $this->assertSame(0, AutorizacaoAcesso::query()->count(), 'Salvar não cria autorização.');
    }

    public function test_visitante_enviado_a_facial_ganha_visita_com_o_responsavel_informado(): void
    {
        $this->usarFilaReal();
        $this->enviarAoTerminal($this->manual()
            ->set('quickName', 'Visitante Facial')
            ->set('quickDocument', self::CPF)
            ->set('quickAccessType', 'visitante')
            ->set('quickResponsible', 'Morador Anfitrião')
            ->set('quickPropertyCode', 'SRA-A-102'))
            ->assertHasNoErrors();

        $visita = AutorizacaoAcesso::query()->sole();
        $this->assertSame('Morador Anfitrião', $visita->responsavel);
        $this->assertNotNull($visita->imovel_id);
        $this->assertLessThanOrEqual(24 * 3600, $visita->inicio->diffInSeconds($visita->fim));
    }

    public function test_turista_entra_por_24_horas_sem_imovel_nem_responsavel(): void
    {
        $this->usarFilaReal();
        $this->enviarAoTerminal($this->manual()
            ->set('quickName', 'Turista Sem Responsavel')
            ->set('quickDocument', self::CPF)
            ->set('quickAccessType', 'turista')
            ->set('quickResponsible', 'Valor Forjado')
            ->call('irParaEtapa', 'foto')
            ->assertHasNoErrors()
            ->set('fotoPessoa', $this->foto()))
            ->assertHasNoErrors()
            ->assertSet('quickResponsible', '')
            ->assertSet('quickResultado.foto.title', 'Foto salva');

        $visita = AutorizacaoAcesso::query()->sole();
        $this->assertNull($visita->responsavel);
        $this->assertNull($visita->imovel_id);
        $this->assertLessThanOrEqual(24 * 3600, $visita->inicio->diffInSeconds($visita->fim));
        $this->assertSame(0, Vinculo::query()->count());
    }

    public function test_visitante_apos_ocr_pede_imovel_e_responsavel(): void
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
            ->assertHasErrors(['quickPropertyCode', 'quickResponsible'])
            ->set('quickResponsible', 'Morador Anfitrião')
            ->set('quickPropertyCode', 'SRA-A-102')
            ->call('irParaEtapa', 'foto')
            ->assertHasNoErrors()
            ->set('fotoPessoa', $this->foto())
            ->call('saveQuickRegistration')
            ->assertSet('quickPersonRegistered', true);

        $this->assertSame('Visitante Lido', Pessoa::query()->sole()->nome);
    }

    public function test_responsavel_residual_ou_forjado_e_descartado_fora_de_visitante_e_morador(): void
    {
        $this->usarFilaReal();
        $tela = $this->manual()
            ->set('quickName', 'Pessoa Trocada')
            ->set('quickDocument', self::CPF)
            ->set('quickAccessType', 'visitante')
            ->set('quickResponsible', 'Responsável Digitado')
            ->set('quickAccessType', 'turista')
            ->assertSet('quickResponsible', '')
            ->set('quickResponsible', 'Responsável Forjado');

        $this->enviarAoTerminal($tela)->assertHasNoErrors()->assertSet('quickResponsible', '');

        $this->assertStringNotContainsString('Forjado', json_encode(DB::table('autorizacoes_acesso')->get()));
        $this->assertStringNotContainsString('Digitado', json_encode(DB::table('autorizacoes_acesso')->get()));
    }

    public function test_morador_mantem_o_responsavel_opcional_e_demais_tipos_salvam_sem_ele(): void
    {
        $this->manual()
            ->set('quickName', 'Morador Sem Responsavel')
            ->set('quickDocument', self::CPF)
            ->set('quickAccessType', 'morador')
            ->set('quickPropertyCode', 'SRA-A-102')
            ->call('saveQuickRegistration')
            ->assertHasNoErrors()
            ->assertSet('quickPersonRegistered', true);

        foreach (['prestador' => '111.444.777-35', 'outro' => '935.411.347-80'] as $tipo => $cpf) {
            $this->manual()
                ->set('quickName', 'Pessoa '.$tipo)
                ->set('quickDocument', $cpf)
                ->set('quickAccessType', $tipo)
                ->call('saveQuickRegistration')
                ->assertHasNoErrors()
                ->assertSet('quickPersonRegistered', true);
        }
        $this->assertSame(0, AutorizacaoAcesso::query()->count());
    }

    public function test_sem_autorizacao_vigente_o_envio_continua_impedido(): void
    {
        $this->usarFilaReal();
        $this->enviarAoTerminal($this->manual()
            ->set('quickName', 'Prestador Sem Imovel')
            ->set('quickDocument', self::CPF)
            ->set('quickAccessType', 'prestador')
            ->call('irParaEtapa', 'foto')
            ->set('fotoPessoa', $this->foto()))
            ->assertSet('quickResultado.cadastro.title', 'Cadastro salvo')
            ->assertSet('quickResultado.foto.title', 'Foto salva')
            ->assertSet('quickResultado.facial.variant', 'danger');

        $this->assertSame(0, AutorizacaoAcesso::query()->count());
        $this->assertSame(0, Operacao::query()->where('operacao', 'sincronizar_credencial')->count());
    }
}
