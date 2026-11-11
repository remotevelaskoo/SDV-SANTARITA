<?php

namespace Tests\Feature\Hibrido;

use App\Models\Implantacao;
use App\Models\PreRegistration;
use App\Services\Hibrido\PreCadastroNuvem;
use App\Services\PrivateFileService;
use App\Support\ImplantacaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PreCadastroNuvemTest extends TestCase
{
    use RefreshDatabase;

    private Implantacao $implantacao;

    private array $item;

    private string $image;

    protected function setUp(): void
    {
        parent::setUp();
        $this->implantacao = Implantacao::factory()->create();
        ImplantacaoContext::setCurrentForTesting($this->implantacao);
        Storage::fake(PrivateFileService::DISK);
        config(['pre_cadastro_nuvem.enabled' => true, 'pre_cadastro_nuvem.url' => 'https://portal.example.test',
            'pre_cadastro_nuvem.token' => str_repeat('n', 40), 'pre_cadastro_nuvem.implantacao_id' => $this->implantacao->id]);
        $image = UploadedFile::fake()->image('sintetica.jpg', 400, 500);
        $this->image = file_get_contents($image->getRealPath());
        $this->item = [
            'id' => (string) Str::uuid(), 'implantacao_id' => $this->implantacao->id,
            'protocol' => 'PRE-SRA-'.strtoupper(str_replace('-', '', (string) Str::uuid())),
            'submitted_at' => now()->subMinute()->toIso8601String(),
            'dados' => ['name' => 'Pessoa Sintética', 'document' => '12345678901', 'document_type' => 'cnh',
                'birth_date' => null, 'phone' => null, 'email' => null, 'access_type' => 'turista',
                'address_informed' => null, 'destination_property' => null, 'destination_label' => 'Praia do Santa Rita',
                'responsible_name' => null, 'vehicle_plate' => null, 'vehicle_model' => null, 'vehicle_color' => null],
            'files' => array_map(fn ($category) => ['category' => $category, 'sha256' => hash('sha256', $this->image), 'size' => strlen($this->image)], ['documento', 'selfie']),
        ];
    }

    protected function tearDown(): void
    {
        ImplantacaoContext::forgetCurrent();
        parent::tearDown();
    }

    private function cloud(bool $ack = true): void
    {
        Http::swap(new Factory);
        Http::fake(function ($request) use ($ack) {
            $this->assertSame('Bearer '.str_repeat('n', 40), $request->header('Authorization')[0]);
            if ($request->method() === 'POST') {
                return Http::response(['acknowledged' => $ack], $ack ? 200 : 503);
            }
            if (str_contains($request->url(), 'action=file')) {
                return Http::response($this->image, 200, ['Content-Type' => 'image/jpeg']);
            }

            return Http::response(['records' => [$this->item]]);
        });
    }

    public function test_importa_na_fila_existente_com_imagens_privadas_sem_criar_pessoa_ou_autorizacao(): void
    {
        $this->cloud();
        $totais = app(PreCadastroNuvem::class)->sincronizar();
        $this->assertSame(0, $totais['falhas']);
        $record = PreRegistration::query()->sole();
        $this->assertSame('aguardando', $record->status);
        $this->assertNull($record->phone);
        $this->assertNull($record->responsible_name);
        $this->assertCount(2, $record->fileLinks);
        foreach ($record->fileLinks as $link) {
            Storage::disk(PrivateFileService::DISK)->assertExists($link->file->object_key);
        }
        $this->assertDatabaseCount('pessoas', 0);
        $this->assertDatabaseCount('autorizacoes_acesso', 0);
        $this->assertDatabaseHas('pre_cadastro_nuvem_recebimentos', ['cloud_id' => $this->item['id'], 'acked_version' => 1]);
    }

    public function test_reenvio_e_retentativa_apos_falha_do_ack_nao_duplicam_nem_apagam_foto(): void
    {
        $this->cloud(false);
        $this->assertGreaterThan(0, app(PreCadastroNuvem::class)->sincronizar()['falhas']);
        $this->assertDatabaseCount('pre_registrations', 1);
        $this->assertDatabaseHas('pre_cadastro_nuvem_recebimentos', ['acked_version' => 0]);
        $this->cloud();
        $this->assertSame(0, app(PreCadastroNuvem::class)->sincronizar()['falhas']);
        $this->assertDatabaseCount('pre_registrations', 1);
        $this->assertDatabaseCount('arquivos', 2);
    }

    public function test_isolamento_de_implantacao_e_integridade_da_imagem(): void
    {
        $this->item['implantacao_id'] = (string) Str::uuid();
        $this->cloud();
        $this->assertSame(1, app(PreCadastroNuvem::class)->sincronizar()['falhas']);
        $this->assertDatabaseCount('pre_registrations', 0);
        $this->item['implantacao_id'] = $this->implantacao->id;
        $this->item['files'][0]['sha256'] = str_repeat('0', 64);
        $this->assertSame(1, app(PreCadastroNuvem::class)->sincronizar()['falhas']);
        $this->assertDatabaseCount('arquivos', 0);
        $this->assertSame([], Storage::disk(PrivateFileService::DISK)->allFiles());
    }

    public function test_verso_do_rg_e_conjunto_de_arquivos_sao_obrigatorios(): void
    {
        $this->item['dados']['document_type'] = 'rg_cin';
        $this->cloud();
        $this->assertSame(1, app(PreCadastroNuvem::class)->sincronizar()['falhas']);
        $this->assertDatabaseCount('pre_registrations', 0);
    }

    public function test_decisao_local_posterior_e_reenviada_sem_sobrescrever_o_registro_local(): void
    {
        $this->cloud();
        app(PreCadastroNuvem::class)->sincronizar();
        $record = PreRegistration::query()->sole();
        $record->update(['status' => 'correcao', 'version' => 2]);
        app(PreCadastroNuvem::class)->sincronizar();
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['status'] === 'correcao' && $request['version'] === 2);
        $this->assertSame('correcao', $record->fresh()->status);
        $this->assertDatabaseHas('pre_cadastro_nuvem_recebimentos', ['acked_version' => 2]);
    }

    public function test_queda_da_nuvem_preserva_cadastros_e_confirmacoes_pendentes(): void
    {
        $this->cloud(false);
        app(PreCadastroNuvem::class)->sincronizar();
        Http::swap(new Factory);
        Http::fake(fn () => Http::response([], 503));
        $this->artisan('pre-cadastro:sincronizar-nuvem')->assertFailed();
        $this->assertDatabaseCount('pre_registrations', 1);
        $this->assertDatabaseCount('arquivos', 2);
        $this->assertSame(0, DB::table('pre_cadastro_nuvem_recebimentos')->value('acked_version'));
    }

    public function test_visitante_exige_imovel_e_responsavel_conforme_revisao_atual_do_po(): void
    {
        $this->item['dados']['access_type'] = 'visitante';
        $this->cloud();
        $this->assertSame(1, app(PreCadastroNuvem::class)->sincronizar()['falhas']);
        $this->assertDatabaseCount('pre_registrations', 0);
        $this->item['dados']['destination_property'] = 'Imóvel de teste';
        $this->item['dados']['destination_label'] = 'Imóvel de teste';
        $this->item['dados']['responsible_name'] = 'Responsável de teste';
        $this->assertSame(0, app(PreCadastroNuvem::class)->sincronizar()['falhas']);
        $this->assertSame('Responsável de teste', PreRegistration::query()->sole()->responsible_name);
        $this->assertDatabaseCount('autorizacoes_acesso', 0);
    }

    public function test_desligado_por_configuracao_nao_envia_requisicoes(): void
    {
        config(['pre_cadastro_nuvem.enabled' => false]);
        Http::fake();
        $this->artisan('pre-cadastro:sincronizar-nuvem')->assertFailed();
        Http::assertNothingSent();
    }
}
