<?php

namespace Tests\Feature\Aj010;

use App\Documentos\Dominio\ResultadoOcr;
use App\Documentos\Infra\OcrSimulador;
use App\Livewire\AccessValidation;
use App\Livewire\PersonRegistration;
use App\Livewire\PublicPreRegistration;
use App\Models\AutorizacaoAcesso;
use App\Models\Imovel;
use App\Models\OperacaoIntegracao as Operacao;
use App\Models\Pessoa;
use App\Models\PessoaArquivo;
use App\Models\User;
use App\Services\PrivateFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\Support\ImagensDocumentoSinteticas;
use Tests\TestCase;

/**
 * AJ-010 §12 — Nome completo por voz no cadastro rápido. O navegador entrega
 * a transcrição final ao Livewire; aqui ela é simulada (sem áudio nem rede).
 * A interação no navegador é coberta em tests/js/nome-por-voz.test.mjs.
 */
class NomePorVozTest extends TestCase
{
    use MontaCenarioIntegracao, RefreshDatabase;

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

    private function conferencia(): Testable
    {
        return Livewire::actingAs($this->operador)->test(AccessValidation::class)
            ->set('notes', 'Atendimento preservado.')
            ->call('openQuickRegistration')
            ->call('digitarManualmente')
            ->set('quickDocument', '529.982.247-25')
            ->set('quickPhone', '(12) 99999-1234')
            ->set('quickAccessType', 'visitante')
            ->set('quickPropertyCode', 'SRA-A-102');
    }

    public function test_microfone_aparece_somente_no_nome_completo_do_cadastro_rapido(): void
    {
        $html = $this->conferencia()->assertSee('Preencher por voz')->html();
        $this->assertSame(1, substr_count($html, 'x-data="nomePorVoz()"'));
        $this->assertMatchesRegularExpression('/x-data="nomePorVoz\(\)"[^>]*>\s*(<!--.*?-->\s*)?<label class="ui-field" for="quick-name"/s', $html);

        // Fora do cadastro rápido não há microfone.
        Livewire::actingAs($this->operador)->test(AccessValidation::class)->assertDontSee('Preencher por voz');
        Livewire::actingAs($this->operador)->test(PersonRegistration::class)->assertDontSee('Preencher por voz');
        Livewire::test(PublicPreRegistration::class)->assertDontSee('Preencher por voz');
    }

    public function test_transcricao_final_preenche_so_o_nome_sem_salvar_nem_enviar(): void
    {
        Log::spy();

        $this->conferencia()
            ->call('preencherNomePorVoz', '  Vinícius   Velasco ')
            ->assertHasNoErrors()
            ->assertSet('quickName', 'Vinícius Velasco')
            ->assertSet('quickDocument', '529.982.247-25')
            ->assertSet('quickPhone', '(12) 99999-1234')
            ->assertSet('quickEtapa', 'conferencia')
            ->assertSet('quickRegistrationOpen', true)
            ->assertSet('quickPersonRegistered', false)
            ->assertSet('notes', 'Atendimento preservado.')
            ->assertSet('camposEditados', fn (array $campos) => in_array('quickName', $campos, true));

        $this->assertSame(0, Pessoa::query()->count());
        $this->assertSame(0, PessoaArquivo::query()->count());
        $this->assertSame(0, AutorizacaoAcesso::query()->count());
        $this->assertSame(0, Operacao::query()->where('operacao', '!=', 'consultar_capacidades')->count());
        $this->assertDatabaseMissing('auditoria_eventos', ['action' => 'pessoa_cadastrada']);
        // A transcrição não vai para a auditoria.
        $this->assertStringNotContainsString('Vinícius', json_encode(DB::table('auditoria_eventos')->get()));
        Log::shouldNotHaveReceived('info');
    }

    public function test_resultado_vazio_preserva_o_nome_e_nova_tentativa_substitui(): void
    {
        $tela = $this->conferencia()->set('quickName', 'Nome Digitado');

        $tela->call('preencherNomePorVoz', '   ')->assertSet('quickName', 'Nome Digitado');
        $tela->call('preencherNomePorVoz', 'Maria Souza')->assertSet('quickName', 'Maria Souza');
        // Correção manual depois da voz continua possível.
        $tela->set('quickName', 'Maria de Souza')->assertSet('quickName', 'Maria de Souza');
        $tela->call('preencherNomePorVoz', 'Maria Aparecida de Souza')->assertSet('quickName', 'Maria Aparecida de Souza')
            ->assertSet('quickPersonRegistered', false);
    }

    public function test_cpf_ditado_preenche_formatado_e_confere_duplicidade(): void
    {
        $html = $this->conferencia()->assertSee('Ditar CPF')->html();
        $this->assertMatchesRegularExpression('/nomePorVoz\(\{ tipo: \'cpf\', metodo: \'preencherCpfPorVoz\' \}\)"[^>]*>\s*<label class="ui-field" for="quick-document"/s', $html);

        $tela = $this->conferencia()->set('quickDocument', '');
        $tela->call('preencherCpfPorVoz', '5299')->assertSet('quickDocument', '');
        $tela->call('preencherCpfPorVoz', '529 982 247 25')
            ->assertSet('quickDocument', '529.982.247-25')
            ->assertSet('quickDuplicateFound', false)
            ->assertSet('camposEditados', fn (array $campos) => in_array('quickDocument', $campos, true));
        $this->assertSame(0, Pessoa::query()->count());
    }

    public function test_nome_ditado_segue_a_validacao_existente(): void
    {
        $this->conferencia()->call('preencherNomePorVoz', 'Al')->assertHasErrors('quickName');
        $this->conferencia()->call('preencherNomePorVoz', str_repeat('Nome ', 40))->assertSet('quickName', fn (string $nome) => mb_strlen($nome) <= 120);
    }

    public function test_ocr_posterior_respeita_o_nome_ditado(): void
    {
        app(OcrSimulador::class)->responderCom(new ResultadoOcr(true, [
            'nome' => ['valor' => 'Nome Lido Pelo Ocr', 'confianca' => 0.99],
            'cpf' => ['valor' => '529.982.247-25', 'confianca' => 0.99],
        ]));

        Livewire::actingAs($this->operador)->test(AccessValidation::class)
            ->call('openQuickRegistration')
            ->call('digitarManualmente')
            ->call('preencherNomePorVoz', 'Nome Ditado Pelo Operador')
            ->call('irParaEtapa', 'documento')
            ->set('capturaFrente', UploadedFile::fake()->createWithContent('frente.jpg', ImagensDocumentoSinteticas::boa()))
            ->assertSet('quickName', 'Nome Ditado Pelo Operador')
            ->assertSee('Mantivemos o que você corrigiu');
    }

    public function test_voz_e_ignorada_fora_da_conferencia_e_exige_permissao(): void
    {
        // Etapa Foto: o campo não está na tela; resultado atrasado não muda o nome.
        $this->conferencia()
            ->set('quickName', 'Nome Conferido')
            ->set('quickResponsible', 'Responsável')
            ->call('irParaEtapa', 'foto')
            ->assertSet('quickEtapa', 'foto')
            ->call('preencherNomePorVoz', 'Outro Nome')
            ->assertSet('quickName', 'Nome Conferido');

        // Atendimento encerrado: nada a preencher.
        $this->conferencia()->call('cancelQuickRegistration')
            ->call('preencherNomePorVoz', 'Nome Atrasado')
            ->assertSet('quickName', '');

        // Sem permissão de cadastro, recusado no servidor.
        Livewire::actingAs($this->usuarioCom('validacao.registrar'))->test(AccessValidation::class)
            ->call('preencherNomePorVoz', 'Sem Permissao')
            ->assertForbidden();
    }

    public function test_voz_nao_cria_autorizacao_e_envio_continua_impedido_sem_imovel(): void
    {
        $terminal = $this->terminalAtivo(['nome' => 'Facial entrada']);
        $this->usarFilaReal();

        $this->conferencia()
            ->set('quickPhone', '')
            ->set('quickAccessType', 'prestador')
            ->set('quickPropertyCode', '')
            ->call('preencherNomePorVoz', 'Prestador Sem Imovel')
            ->call('irParaEtapa', 'foto')
            ->set('quickTerminalId', $terminal->id)
            ->set('quickTerminalConfirmado', true)
            ->call('saveQuickRegistrationAndSend')
            ->assertSet('quickResultado.cadastro.title', 'Cadastro salvo')
            ->assertSet('quickResultado.facial.variant', 'danger');

        $this->assertSame('Prestador Sem Imovel', Pessoa::query()->sole()->nome);
        $this->assertSame(0, AutorizacaoAcesso::query()->count());
        $this->assertSame(0, $this->simulador()->totalAberturasFisicas($terminal->id));
    }
}
