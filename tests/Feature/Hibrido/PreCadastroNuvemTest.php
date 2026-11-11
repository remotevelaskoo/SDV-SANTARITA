<?php

namespace Tests\Feature\Hibrido;

use App\Livewire\PreRegistrationQueue;
use App\Models\Imovel;
use App\Models\Implantacao;
use App\Models\PreRegistration;
use App\Services\Hibrido\PreCadastroNuvem;
use App\Services\PrivateFileService;
use App\Support\ImplantacaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\DestinosDeTeste;
use Tests\Support\OperadorComPermissoes;
use Tests\TestCase;

class PreCadastroNuvemTest extends TestCase
{
    use OperadorComPermissoes, RefreshDatabase;

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

    /** @var list<array<string, mixed>> */
    private array $enviados = [];

    private int $tentativas = 0;

    private function cloud(bool $ack = true): void
    {
        Http::swap(new Factory);
        Http::fake(function ($request) use ($ack) {
            $this->assertSame('Bearer '.str_repeat('n', 40), $request->header('Authorization')[0]);
            if ($request->method() === 'POST') {
                $this->enviados[] = $request->data();

                return match ($request['action']) {
                    'catalogo' => Http::response(['stored' => count($request['itens'])]),
                    'falha' => Http::response(['state' => ++$this->tentativas >= 5 ? 'conferencia' : 'enviado', 'attempts' => $this->tentativas]),
                    'reabrir' => Http::response(['reopened' => true]),
                    default => Http::response(['acknowledged' => $ack], $ack ? 200 : 503),
                };
            }
            if (str_contains($request->url(), 'action=file')) {
                return Http::response($this->image, 200, ['Content-Type' => 'image/jpeg']);
            }

            return Http::response(['records' => [$this->item]]);
        });
    }

    /** @return list<array<string, mixed>> */
    private function enviadosCom(string $action): array
    {
        return array_values(array_filter($this->enviados, fn ($body) => ($body['action'] ?? null) === $action));
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
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['action'] === 'ack' && $request['status'] === 'correcao' && $request['version'] === 2);
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

    public function test_catalogo_vem_do_cadastro_local_sem_identificadores_e_sem_imovel_inativo(): void
    {
        DestinosDeTeste::criar(['Bloco B — Apto 304' => 'Mariana Souza', 'Bloco C — Loja 1' => null]);
        Imovel::factory()->create(['status' => 'bloqueado', 'codigo' => 'SRA-X-999']);
        $this->cloud();
        app(PreCadastroNuvem::class)->sincronizar();
        $catalogo = $this->enviadosCom('catalogo');
        $this->assertCount(1, $catalogo);
        $this->assertSame([
            ['label' => 'Bloco B — Apto 304', 'responsible' => 'Mariana Souza'],
            ['label' => 'Bloco C — Loja 1', 'responsible' => null],
        ], $catalogo[0]['itens']);
        // Sem mudança local, não republica no ciclo seguinte.
        app(PreCadastroNuvem::class)->sincronizar();
        $this->assertCount(1, $this->enviadosCom('catalogo'));
        Cache::flush();
        DestinosDeTeste::criar(['Bloco D — Apto 1' => 'Outra Pessoa']);
        app(PreCadastroNuvem::class)->sincronizar();
        $this->assertCount(3, $this->enviadosCom('catalogo')[1]['itens']);
    }

    public function test_falha_repetida_vai_para_conferencia_sem_dados_pessoais_e_pode_ser_reaberta(): void
    {
        $this->item['dados']['email'] = 'nao-e-um-email';
        $this->cloud();
        for ($i = 1; $i <= 5; $i++) {
            $totais = app(PreCadastroNuvem::class)->sincronizar();
        }
        $this->assertSame(1, $totais['conferencia']);
        $this->assertDatabaseCount('pre_registrations', 0);
        $falha = DB::table('pre_cadastro_nuvem_falhas')->sole();
        $this->assertSame('conferencia', $falha->situacao);
        $this->assertSame('dados_invalidos', $falha->codigo);
        $this->assertSame(['dados.email'], json_decode($falha->campos, true));
        $this->assertSame(5, (int) $falha->tentativas);
        $this->assertSame($this->item['protocol'], $falha->protocol);
        // Nada do conteúdo enviado é gravado na falha nem mandado à nuvem.
        $this->assertStringNotContainsString('nao-e-um-email', json_encode($falha));
        $this->assertStringNotContainsString('Sintética', json_encode($this->enviadosCom('falha')));
        $this->assertSame(['action' => 'falha', 'id' => $this->item['id'], 'code' => 'dados_invalidos'], $this->enviadosCom('falha')[0]);

        // A fila sinaliza; quem pode editar pede nova tentativa, que vai à nuvem na próxima sincronização.
        $user = $this->operador('pre-registro.analisar', 'pre-registro.editar');
        $this->actingAs($user);
        Livewire::test(PreRegistrationQueue::class)
            ->assertSee('1 envio do portal precisa de conferência')
            ->assertSee($this->item['protocol'])
            ->assertSee('Dados fora das regras atuais')
            ->assertSee('(e-mail)')
            ->call('tentarNovamente', $falha->id);
        $this->assertSame('reabertura_solicitada', DB::table('pre_cadastro_nuvem_falhas')->value('situacao'));

        $this->item['dados']['email'] = null;
        app(PreCadastroNuvem::class)->sincronizar();
        $this->assertSame(['action' => 'reabrir', 'id' => $this->item['id']], $this->enviadosCom('reabrir')[0]);
        $this->assertDatabaseCount('pre_registrations', 1);
        $this->assertSame('resolvida', DB::table('pre_cadastro_nuvem_falhas')->value('situacao'));
        $this->assertDatabaseCount('autorizacoes_acesso', 0);
    }

    public function test_sem_permissao_de_editar_nao_reabre(): void
    {
        DB::table('pre_cadastro_nuvem_falhas')->insert(['id' => $id = (string) Str::uuid(), 'implantacao_id' => $this->implantacao->id,
            'cloud_id' => (string) Str::uuid(), 'codigo' => 'imagem_invalida', 'tentativas' => 5, 'situacao' => 'conferencia',
            'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->operador('pre-registro.analisar'));
        Livewire::test(PreRegistrationQueue::class)
            ->assertSee('Imagem ilegível ou fora do formato')
            ->assertDontSee('Tentar novamente')
            ->call('tentarNovamente', $id)
            ->assertForbidden();
        $this->assertSame('conferencia', DB::table('pre_cadastro_nuvem_falhas')->value('situacao'));
    }

    public function test_periodo_usa_a_data_prevista_da_visita_e_recusa_data_fora_da_janela(): void
    {
        $this->item['dados']['visit_date'] = now('America/Sao_Paulo')->addDays(3)->toDateString();
        $this->cloud();
        app(PreCadastroNuvem::class)->sincronizar();
        $record = PreRegistration::query()->sole();
        $inicio = now('America/Sao_Paulo')->addDays(3)->startOfDay();
        $this->assertTrue($record->period_start->equalTo($inicio));
        $this->assertTrue($record->period_end->equalTo($inicio->copy()->addDay()));

        $this->item['id'] = (string) Str::uuid();
        $this->item['dados']['visit_date'] = now('America/Sao_Paulo')->addDays(40)->toDateString();
        $this->assertSame(1, app(PreCadastroNuvem::class)->sincronizar()['falhas']);
        $this->assertSame(['dados.visit_date'], json_decode(DB::table('pre_cadastro_nuvem_falhas')->value('campos'), true));
        $this->assertDatabaseCount('pre_registrations', 1);
    }

    public function test_pequena_diferenca_de_relogio_nao_impede_importacao(): void
    {
        $this->item['submitted_at'] = now()->addMinutes(3)->toIso8601String();
        $this->cloud();
        $this->assertSame(0, app(PreCadastroNuvem::class)->sincronizar()['falhas']);
        $this->assertDatabaseCount('pre_registrations', 1);
    }
}
