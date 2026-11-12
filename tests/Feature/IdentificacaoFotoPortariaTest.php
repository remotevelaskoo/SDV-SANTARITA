<?php

namespace Tests\Feature;

use App\Livewire\AccessValidation;
use App\Models\Pessoa;
use App\Models\PessoaArquivo;
use App\Services\PrivateFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\Support\ImagensPessoaSinteticas;
use Tests\TestCase;

/**
 * Identificação da pessoa na validação: sem foto no cadastro, a portaria
 * fotografa ali mesmo. Só imagens sintéticas; nada vai ao terminal facial.
 */
class IdentificacaoFotoPortariaTest extends TestCase
{
    use MontaCenarioIntegracao, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararIntegracao();
        Storage::fake(PrivateFileService::DISK);
    }

    protected function tearDown(): void
    {
        $this->encerrarIntegracao();
        parent::tearDown();
    }

    private function foto(string $conteudo): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('pessoa.jpg', $conteudo);
    }

    public function test_pessoa_sem_foto_abre_a_camera_e_salva_no_cadastro(): void
    {
        $pessoa = Pessoa::factory()->create();
        $operador = $this->usuarioCom('validacao.registrar', 'pessoas.gerenciar', 'arquivos.sensiveis.visualizar');

        // Atendimento guiado: a foto é tirada na etapa Foto e só é gravada no Concluir.
        $tela = Livewire::actingAs($operador)->test(AccessValidation::class)
            ->call('selecionarPessoa', $pessoa->id)
            ->call('continuar')
            ->assertSet('passo', 'foto')
            ->assertSee('Pessoa sem foto')
            ->assertSee('Abrir câmera')
            ->call('continuar')
            ->assertHasErrors('fotoIdentificacao')
            ->assertSet('passo', 'foto')
            ->set('fotoIdentificacao', $this->foto(ImagensPessoaSinteticas::boa()))
            ->assertHasNoErrors()
            ->call('continuar')
            ->assertSet('passo', 'placa');
        $this->assertFalse(PessoaArquivo::query()->where('pessoa_id', $pessoa->id)->exists(), 'Nada é gravado antes do Concluir.');

        $tela->set('veioDeCarro', false)
            ->call('continuar')
            ->assertSet('passo', 'concluir')
            ->call('concluir', 'pendente')
            ->assertHasNoErrors()
            ->assertSet('fotoIdentificacao', null)
            ->assertSee('Foto adicionada ao cadastro');

        $link = PessoaArquivo::query()->where('pessoa_id', $pessoa->id)->where('category', 'foto')->where('is_current', true)->sole();
        $this->assertSame('validacao_portaria', $link->file->origin);
        Storage::disk(PrivateFileService::DISK)->assertExists($link->file->object_key);
    }

    public function test_foto_escura_e_recusada_para_refazer(): void
    {
        $pessoa = Pessoa::factory()->create();

        Livewire::actingAs($this->usuarioCom('validacao.registrar', 'pessoas.gerenciar'))->test(AccessValidation::class)
            ->call('selecionarPessoa', $pessoa->id)
            ->set('fotoIdentificacao', $this->foto(ImagensPessoaSinteticas::escura()))
            ->assertHasErrors('fotoIdentificacao')
            ->assertSet('fotoIdentificacao', null)
            ->call('salvarFotoIdentificacao')
            ->assertHasErrors('fotoIdentificacao');

        $this->assertFalse(PessoaArquivo::query()->where('pessoa_id', $pessoa->id)->exists());
    }

    public function test_pessoa_com_foto_nao_mostra_a_camera(): void
    {
        $pessoa = Pessoa::factory()->create();
        app(PrivateFileService::class)->storePessoaFoto($pessoa, $this->foto(ImagensPessoaSinteticas::boa()));

        Livewire::actingAs($this->usuarioCom('validacao.registrar', 'pessoas.gerenciar', 'arquivos.sensiveis.visualizar'))->test(AccessValidation::class)
            ->call('selecionarPessoa', $pessoa->id)
            ->assertDontSee('Pessoa sem foto: tire a foto agora')
            ->set('fotoIdentificacao', $this->foto(ImagensPessoaSinteticas::outra()))
            ->call('salvarFotoIdentificacao')
            ->assertHasErrors('fotoIdentificacao');

        $this->assertSame(1, PessoaArquivo::query()->where('pessoa_id', $pessoa->id)->count());
    }

    public function test_sem_permissao_de_gerenciar_pessoas_nao_fotografa(): void
    {
        $pessoa = Pessoa::factory()->create();

        Livewire::actingAs($this->usuarioCom('validacao.registrar'))->test(AccessValidation::class)
            ->call('selecionarPessoa', $pessoa->id)
            ->assertDontSee('Pessoa sem foto: tire a foto agora')
            ->call('salvarFotoIdentificacao')
            ->assertForbidden();

        $this->assertFalse(PessoaArquivo::query()->where('pessoa_id', $pessoa->id)->exists());
    }

    public function test_trocar_de_pessoa_descarta_a_foto_em_andamento(): void
    {
        $pessoa = Pessoa::factory()->create();

        Livewire::actingAs($this->usuarioCom('validacao.registrar', 'pessoas.gerenciar'))->test(AccessValidation::class)
            ->call('selecionarPessoa', $pessoa->id)
            ->set('fotoIdentificacao', $this->foto(ImagensPessoaSinteticas::boa()))
            ->call('trocarPessoa')
            ->assertSet('fotoIdentificacao', null)
            ->assertDispatched('foto-identificacao-encerrada');

        $this->assertFalse(PessoaArquivo::query()->where('pessoa_id', $pessoa->id)->exists());
    }
}
