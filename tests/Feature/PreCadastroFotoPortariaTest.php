<?php

namespace Tests\Feature;

use App\Livewire\PreRegistrationQueue;
use App\Livewire\PreRegistrationReview;
use App\Models\AuditoriaEvento;
use App\Models\Implantacao;
use App\Models\PreRegistration;
use App\Models\PreRegistrationArquivo;
use App\Models\PreRegistrationEdit;
use App\Services\PrivateFileService;
use App\Support\ImplantacaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\ImagensPessoaSinteticas;
use Tests\Support\OperadorComPermissoes;
use Tests\TestCase;

/**
 * Foto da pessoa no pré-cadastro: a fila mostra a foto, e a portaria tira a
 * foto na análise quando o solicitante não enviou selfie. Só dados sintéticos.
 */
class PreCadastroFotoPortariaTest extends TestCase
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

    private function foto(string $conteudo): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('pessoa.jpg', $conteudo);
    }

    public function test_fila_mostra_a_foto_de_quem_tem_selfie_e_aponta_quem_esta_sem(): void
    {
        $comFoto = PreRegistration::factory()->create(['name' => 'Pessoa Com Foto']);
        [$selfie] = app(PrivateFileService::class)->storePreRegistrationFiles($comFoto, [
            'selfie' => UploadedFile::fake()->image('selfie.jpg', 480, 640),
        ]);
        $semFoto = PreRegistration::factory()->create(['name' => 'Pessoa Sem Foto']);

        $html = Livewire::actingAs($this->operador('pre-registro.analisar', 'pre-registro.editar', 'arquivos.sensiveis.visualizar'))
            ->test(PreRegistrationQueue::class)
            ->assertSee('Sem foto · tirar agora')
            ->html();

        $this->assertStringContainsString(e(route('protected-files.show', ['arquivo' => $selfie, 'contexto' => 'fila_pre_cadastro'])), $html);
        $this->assertStringContainsString(e(route('pre-registrations.review', $semFoto->id)).'#tirar-foto', $html);
    }

    public function test_fila_sem_permissao_de_imagens_nao_gera_url_da_foto(): void
    {
        $registro = PreRegistration::factory()->create();
        [$selfie] = app(PrivateFileService::class)->storePreRegistrationFiles($registro, [
            'selfie' => UploadedFile::fake()->image('selfie.jpg', 480, 640),
        ]);

        $html = Livewire::actingAs($this->operador('pre-registro.analisar'))
            ->test(PreRegistrationQueue::class)
            ->assertDontSee('tirar agora')
            ->html();

        $this->assertStringNotContainsString($selfie->id, $html);
    }

    public function test_analise_sem_selfie_oferece_a_camera_e_salva_a_foto_protegida(): void
    {
        $registro = PreRegistration::factory()->create();
        $operador = $this->operador('pre-registro.analisar', 'pre-registro.editar', 'arquivos.sensiveis.visualizar');

        Livewire::actingAs($operador)
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->assertSee('Tirar a foto da pessoa')
            ->assertSee('Abrir câmera')
            ->set('fotoPessoa', $this->foto(ImagensPessoaSinteticas::boa()))
            ->assertHasNoErrors()
            ->call('salvarFotoPessoa')
            ->assertHasNoErrors()
            ->assertSet('fotoPessoa', null)
            ->assertDispatched('foto-pre-cadastro-salva')
            ->assertSee('Foto adicionada')
            ->assertDontSee('Tirar a foto da pessoa')
            ->assertSee('Foto tirada pela portaria');

        $link = PreRegistrationArquivo::query()->where('pre_registration_id', $registro->id)->where('category', 'selfie')->where('is_current', true)->sole();
        $this->assertSame('portaria_pre_cadastro', $link->file->origin);
        $this->assertSame('sensivel', $link->file->classification);
        Storage::disk(PrivateFileService::DISK)->assertExists($link->file->object_key);

        $this->assertDatabaseHas('pre_registration_edits', [
            'pre_registration_id' => $registro->id,
            'action' => 'foto_adicionada',
            'reason' => 'Foto tirada pela portaria',
            'operator_id' => $operador->id,
        ]);
        $this->assertTrue(AuditoriaEvento::query()->where('action', 'recebeu_arquivos_protegidos')->where('entity_id', $registro->id)->exists());
        $this->assertSame(1, $registro->fresh()->version, 'tirar a foto não muda a versão dos dados');
    }

    public function test_foto_escura_e_recusada_para_refazer(): void
    {
        $registro = PreRegistration::factory()->create();

        Livewire::actingAs($this->operador('pre-registro.analisar', 'pre-registro.editar'))
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->set('fotoPessoa', $this->foto(ImagensPessoaSinteticas::escura()))
            ->assertHasErrors('fotoPessoa')
            ->assertSet('fotoPessoa', null)
            ->call('salvarFotoPessoa')
            ->assertHasErrors('fotoPessoa');

        $this->assertFalse(PreRegistrationArquivo::query()->where('pre_registration_id', $registro->id)->exists());
    }

    public function test_nova_foto_substitui_a_selfie_e_preserva_a_anterior(): void
    {
        $registro = PreRegistration::factory()->create();
        [$antiga] = app(PrivateFileService::class)->storePreRegistrationFiles($registro, [
            'selfie' => UploadedFile::fake()->image('selfie.jpg', 480, 640),
        ]);

        Livewire::actingAs($this->operador('pre-registro.analisar', 'pre-registro.editar', 'arquivos.sensiveis.visualizar'))
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->assertSee('Foto ruim? Tirar nova foto')
            ->set('fotoPessoa', $this->foto(ImagensPessoaSinteticas::boa()))
            ->call('salvarFotoPessoa')
            ->assertSee('Foto substituída');

        $links = PreRegistrationArquivo::query()->where('pre_registration_id', $registro->id)->where('category', 'selfie')->get();
        $this->assertCount(2, $links);
        $this->assertFalse($links->firstWhere('arquivo_id', $antiga->id)->is_current);
        Storage::disk(PrivateFileService::DISK)->assertExists($antiga->object_key);
        $this->assertSame('Foto substituída pela portaria', PreRegistrationEdit::query()->where('action', 'foto_adicionada')->sole()->reason);
    }

    public function test_sem_permissao_de_edicao_nao_ve_a_camera_nem_salva(): void
    {
        $registro = PreRegistration::factory()->create();

        Livewire::actingAs($this->operador('pre-registro.analisar'))
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->assertDontSee('Tirar a foto da pessoa')
            ->set('fotoPessoa', $this->foto(ImagensPessoaSinteticas::boa()))
            ->assertHasErrors('fotoPessoa')
            ->call('salvarFotoPessoa')
            ->assertForbidden();

        $this->assertFalse(PreRegistrationArquivo::query()->where('pre_registration_id', $registro->id)->exists());
    }

    public function test_pre_cadastro_rejeitado_nao_aceita_foto(): void
    {
        $registro = PreRegistration::factory()->create(['status' => 'rejeitado']);

        Livewire::actingAs($this->operador('pre-registro.analisar', 'pre-registro.editar'))
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->assertDontSee('Tirar a foto da pessoa')
            ->call('salvarFotoPessoa')
            ->assertForbidden();
    }
}
