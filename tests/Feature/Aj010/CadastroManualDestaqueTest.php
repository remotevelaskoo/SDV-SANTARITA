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
use App\Models\User;
use App\Models\Vinculo;
use App\Services\PrivateFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\Support\ImagensDocumentoSinteticas;
use Tests\Support\ImagensPessoaSinteticas;
use Tests\TestCase;

/**
 * AJ-010 §13 — "Cadastrar manualmente" em destaque na abertura do cadastro
 * rápido, reaproveitando o fluxo manual existente.
 */
class CadastroManualDestaqueTest extends TestCase
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

    public function test_abertura_mostra_as_duas_entradas_antes_da_camera(): void
    {
        $html = Livewire::actingAs($this->operador)->test(AccessValidation::class)
            ->call('openQuickRegistration')
            ->assertSet('quickEtapa', 'documento')
            ->assertSee('Fotografar documento')
            ->assertSee('Cadastrar manualmente')
            ->assertDontSee('Digitar os dados manualmente')
            ->html();

        $manual = strpos($html, 'Cadastrar manualmente');
        $this->assertNotFalse($manual);
        $this->assertLessThan(strpos($html, 'Câmera de documentos'), $manual, 'A entrada manual vem antes da câmera.');
        $this->assertLessThan(strpos($html, 'Enviar arquivo (contingência)'), $manual);
        // Botão de verdade, fora de <details>, ligado ao fluxo manual existente.
        $this->assertMatchesRegularExpression('/<button[^>]*wire:click="digitarManualmente"[^>]*>\s*<span class="ui-button__content">Cadastrar manualmente<\/span>/', $html);
        $this->assertSame(1, substr_count($html, 'wire:click="digitarManualmente"'));
    }

    public function test_cadastrar_manualmente_abre_a_conferencia_com_foco_no_nome_sem_gravar_nada(): void
    {
        Livewire::actingAs($this->operador)->test(AccessValidation::class)
            ->set('notes', 'Atendimento preservado.')
            ->call('openQuickRegistration')
            ->set('quickName', 'Nome Ja Digitado')
            ->set('quickPhone', '(12) 99999-1234')
            ->call('digitarManualmente')
            ->assertSet('quickEtapa', 'conferencia')
            ->assertDispatched('cadastro-rapido-foco-nome')
            ->assertSee('Preencher por voz')
            ->assertSet('quickName', 'Nome Ja Digitado')
            ->assertSet('quickPhone', '(12) 99999-1234')
            ->assertSet('notes', 'Atendimento preservado.')
            ->assertSet('quickPersonRegistered', false);

        $this->assertSame(0, Pessoa::query()->count());
        $this->assertSame(0, Vinculo::query()->count());
        $this->assertSame(0, AutorizacaoAcesso::query()->count());
        $this->assertSame(0, Operacao::query()->where('operacao', '!=', 'consultar_capacidades')->count());
    }

    public function test_leitura_atrasada_nao_sobrescreve_o_que_foi_digitado_depois_da_troca(): void
    {
        app(OcrSimulador::class)->responderCom(new ResultadoOcr(true, [
            'nome' => ['valor' => 'Nome Lido Atrasado', 'confianca' => 0.99],
            'cpf' => ['valor' => '529.982.247-25', 'confianca' => 0.99],
        ]));

        Livewire::actingAs($this->operador)->test(AccessValidation::class)
            ->call('openQuickRegistration')
            ->call('digitarManualmente')
            ->set('quickName', 'Nome Digitado')
            // Upload da frente que chega depois de o operador escolher a entrada manual.
            ->set('capturaFrente', UploadedFile::fake()->createWithContent('frente.jpg', ImagensDocumentoSinteticas::boa()))
            ->assertSet('quickName', 'Nome Digitado')
            ->assertSet('quickDocument', '')
            ->assertSet('capturaFrente', null)
            ->assertSet('analiseDocumento', null)
            ->assertSet('quickEtapa', 'conferencia')
            ->call('analisarDocumento')
            ->assertSet('quickName', 'Nome Digitado')
            ->assertSet('analiseDocumento', null);
    }

    public function test_fluxo_manual_segue_para_a_foto_e_salva_sem_telefone_e_sem_imovel(): void
    {
        Livewire::actingAs($this->operador)->test(AccessValidation::class)
            ->call('openQuickRegistration')
            ->call('digitarManualmente')
            ->set('quickName', 'Pessoa Manual')
            ->set('quickDocument', '529.982.247-25')
            ->set('quickAccessType', 'turista')
            ->call('irParaEtapa', 'foto')
            ->assertSet('quickEtapa', 'foto')
            ->set('fotoPessoa', UploadedFile::fake()->createWithContent('pessoa.jpg', ImagensPessoaSinteticas::boa()))
            ->call('saveQuickRegistration')
            ->assertHasNoErrors()
            ->assertSet('quickResultado.cadastro.title', 'Cadastro salvo')
            ->assertSet('quickResultado.foto.title', 'Foto salva');

        $this->assertSame(1, PessoaArquivo::query()->count());
        $this->assertSame(0, Vinculo::query()->count());
        $this->assertSame(0, AutorizacaoAcesso::query()->count());
    }

    public function test_entrada_manual_exige_permissao_de_cadastro(): void
    {
        Livewire::actingAs($this->usuarioCom('validacao.registrar'))->test(AccessValidation::class)
            ->call('digitarManualmente')
            ->assertForbidden();
    }
}
