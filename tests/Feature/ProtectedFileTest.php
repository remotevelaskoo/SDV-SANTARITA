<?php

namespace Tests\Feature;

use App\Livewire\AccessValidation;
use App\Livewire\PreRegistrationQueue;
use App\Livewire\PublicPreRegistration;
use App\Models\Arquivo;
use App\Models\AuditoriaEvento;
use App\Models\Implantacao;
use App\Models\Perfil;
use App\Models\Permissao;
use App\Models\Pessoa;
use App\Models\PessoaDocumento;
use App\Models\PreRegistration;
use App\Models\User;
use App\Models\UsuarioPerfil;
use App\Models\Vinculo;
use App\Services\PrivateFileService;
use App\Support\ImplantacaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ProtectedFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        Storage::fake(PrivateFileService::DISK);
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        ImplantacaoContext::forgetCurrent();

        parent::tearDown();
    }

    public function test_files_are_stored_privately_with_opaque_keys_and_catalog_metadata(): void
    {
        $preRegistration = PreRegistration::factory()->create([
            'name' => 'Marina Protegida',
            'document' => '123.456.789-00',
        ]);

        [$document, $selfie] = app(PrivateFileService::class)->storePreRegistrationFiles($preRegistration, [
            'documento' => UploadedFile::fake()->image('cpf-marina.jpg', 640, 480),
            'selfie' => UploadedFile::fake()->image('rosto-marina.png', 480, 640),
        ]);

        Storage::disk(PrivateFileService::DISK)->assertExists($document->object_key);
        Storage::disk(PrivateFileService::DISK)->assertExists($selfie->object_key);
        Storage::disk('public')->assertMissing($document->object_key);

        $this->assertStringNotContainsString('Marina', $document->object_key);
        $this->assertStringNotContainsString('123.456', $document->object_key);
        $this->assertSame('image/jpeg', $document->detected_mime);
        $this->assertSame(64, strlen($document->checksum_sha256));
        $this->assertSame('cpf-marina.jpg', $document->original_name);
        $this->assertNotSame(
            'cpf-marina.jpg',
            DB::table('arquivos')->where('id', $document->id)->value('original_name'),
        );

        $this->assertDatabaseHas('pre_registration_arquivos', [
            'pre_registration_id' => $preRegistration->id,
            'arquivo_id' => $selfie->id,
            'category' => 'selfie',
            'is_current' => true,
        ]);
    }

    public function test_public_upload_rejects_an_image_larger_than_eight_megabytes(): void
    {
        Livewire::test(PublicPreRegistration::class)
            ->set('documentFile', UploadedFile::fake()->image('grande.jpg')->size(8193))
            ->assertHasErrors(['documentFile' => 'max']);
    }

    public function test_replacing_a_file_preserves_the_previous_version_and_object(): void
    {
        $preRegistration = PreRegistration::factory()->create();
        [$first] = app(PrivateFileService::class)->storePreRegistrationFiles($preRegistration, [
            'documento' => UploadedFile::fake()->image('primeiro.jpg'),
        ]);
        [$replacement] = app(PrivateFileService::class)->storePreRegistrationFiles($preRegistration, [
            'documento' => UploadedFile::fake()->image('substituto.jpg'),
        ]);

        $this->assertNotSame($first->id, $replacement->id);
        Storage::disk(PrivateFileService::DISK)->assertExists($first->object_key);
        Storage::disk(PrivateFileService::DISK)->assertExists($replacement->object_key);
        $this->assertDatabaseHas('pre_registration_arquivos', [
            'arquivo_id' => $first->id,
            'is_current' => false,
        ]);
        $this->assertDatabaseHas('pre_registration_arquivos', [
            'arquivo_id' => $replacement->id,
            'is_current' => true,
        ]);
    }

    public function test_protected_route_requires_login_and_the_specific_permission(): void
    {
        [$file] = $this->storedDocument();

        $this->get(route('protected-files.show', $file))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('protected-files.show', $file))
            ->assertRedirect(route('dashboard'));
    }

    public function test_authorized_view_is_inline_not_cached_and_audited_without_storage_secrets(): void
    {
        [$file, $preRegistration] = $this->storedDocument();
        $operator = $this->userWithPermission('arquivos.sensiveis.visualizar');

        $response = $this->actingAs($operator)->get(route('protected-files.show', $file));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));

        $event = AuditoriaEvento::query()
            ->where('action', 'visualizou_arquivo_protegido')
            ->where('entity_id', $file->id)
            ->with('context')
            ->sole();

        $this->assertSame($operator->id, $event->actor_id);
        $this->assertSame($preRegistration->id, $event->context->metadata['pre_registration_id']);
        $auditPayload = json_encode($event->context->metadata, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($file->object_key, $auditPayload);
        $this->assertStringNotContainsString($file->original_name, $auditPayload);
    }

    public function test_an_unavailable_file_is_not_served_and_the_attempt_is_audited(): void
    {
        [$file] = $this->storedDocument();
        $file->update(['status' => 'quarentena']);
        $operator = $this->userWithPermission('arquivos.sensiveis.visualizar');

        $this->actingAs($operator)
            ->get(route('protected-files.show', $file))
            ->assertNotFound();

        $this->assertDatabaseHas('auditoria_eventos', [
            'action' => 'visualizou_arquivo_protegido',
            'entity_id' => $file->id,
            'result' => 'negado',
            'reason_code' => 'arquivo_indisponivel',
        ]);
    }

    public function test_files_from_another_implantation_are_invisible(): void
    {
        $current = ImplantacaoContext::current();
        $operator = $this->userWithPermission('arquivos.sensiveis.visualizar');

        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        [$otherFile] = $this->storedDocument();
        ImplantacaoContext::setCurrentForTesting($current);

        $this->actingAs($operator)
            ->get('/arquivos/'.$otherFile->id.'/visualizar')
            ->assertNotFound();
    }

    public function test_queue_only_loads_the_image_after_an_explicit_authorized_click(): void
    {
        [$file] = $this->storedDocument();
        $operator = $this->userWithPermission(
            'pre-registro.analisar',
            'arquivos.sensiveis.visualizar',
        );

        Livewire::actingAs($operator)
            ->test(PreRegistrationQueue::class)
            ->assertSee('Conferência visual protegida')
            ->assertSee('Abrir documento')
            ->assertSee($file->id, escape: false)
            ->assertSee('previewUrl =', escape: false)
            ->assertDontSee('src="'.route('protected-files.show', $file), escape: false)
            ->assertDontSee($file->object_key, escape: false);
    }

    public function test_entry_validation_finds_files_from_the_latest_approved_pre_registration(): void
    {
        $person = Pessoa::factory()->create();
        PessoaDocumento::factory()->for($person)->create([
            'tipo' => 'cpf',
            'valor_normalizado' => '12345678900',
            'valor_apresentacao' => '123.456.789-00',
            'status' => 'ativo',
            'ended_at' => null,
        ]);
        // currentPessoaId é #[Locked] (não pode ser adulterado via payload
        // Livewire) — para o componente selecionar esta pessoa no mount(),
        // ela precisa ser a única com vínculo ativo neste teste isolado.
        Vinculo::factory()->for($person, 'pessoa')->create();
        $preRegistration = PreRegistration::factory()->withStatus('aprovado')->create([
            'document' => '123.456.789-00',
        ]);
        [$file] = app(PrivateFileService::class)->storePreRegistrationFiles($preRegistration, [
            'documento' => UploadedFile::fake()->image('documento-validacao.jpg'),
        ]);
        $operator = $this->userWithPermission('arquivos.sensiveis.visualizar');

        Livewire::actingAs($operator)
            ->test(AccessValidation::class)
            ->assertSee('Conferência visual protegida')
            ->assertSee('Abrir documento')
            ->assertSee($file->id, escape: false)
            ->assertDontSee($file->object_key, escape: false);
    }

    /** @return array{Arquivo, PreRegistration} */
    private function storedDocument(): array
    {
        $preRegistration = PreRegistration::factory()->create();
        [$file] = app(PrivateFileService::class)->storePreRegistrationFiles($preRegistration, [
            'documento' => UploadedFile::fake()->image('documento.jpg', 640, 480),
        ]);

        return [$file, $preRegistration];
    }

    private function userWithPermission(string ...$keys): User
    {
        $user = User::factory()->create();
        $profile = Perfil::factory()->create();

        foreach ($keys as $key) {
            $permission = Permissao::factory()->create(['chave' => $key]);
            $profile->permissoes()->attach($permission->id, [
                'id' => (string) Str::uuid7(),
                'implantacao_id' => ImplantacaoContext::current()->id,
            ]);
        }

        UsuarioPerfil::factory()->for($user)->for($profile)->create();

        return $user;
    }
}
