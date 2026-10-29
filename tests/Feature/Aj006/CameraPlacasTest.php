<?php

namespace Tests\Feature\Aj006;

use App\Integracoes\Aplicacao\CamerasPlaca;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Livewire\AccessValidation;
use App\Livewire\CameraPlacasManagement;
use App\Livewire\EquipamentoManagement;
use App\Models\AuditoriaEvento;
use App\Models\CaixaTurno;
use App\Models\CapturaPlaca;
use App\Models\Equipamento;
use App\Models\HistoricoAcesso;
use App\Models\Implantacao;
use App\Models\IntegracaoOcorrencia;
use App\Models\Pessoa;
use App\Services\PrivateFileService;
use App\Support\ImplantacaoContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LogicException;
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\TestCase;

/**
 * AJ-006, fatia 5 — câmera IP de placas. Nenhuma câmera real: respostas
 * HTTP simuladas e o simulador de imagem sintética.
 */
class CameraPlacasTest extends TestCase
{
    use MontaCenarioIntegracao, RefreshDatabase;

    private const IP_TESTE = '192.168.50.31';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararIntegracao();
        Storage::fake(PrivateFileService::DISK);
        Cache::flush();
        putenv('SDV_TESTE_CAMERA_SENHA=Senha-Camera-Teste-555');
    }

    protected function tearDown(): void
    {
        putenv('SDV_TESTE_CAMERA_SENHA');
        $this->encerrarIntegracao();
        parent::tearDown();
    }

    public function test_simulador_captura_evidencia_marcada_e_liga_a_decisao_de_forma_imutavel(): void
    {
        $camera = $this->cameraSimulada();
        $operador = $this->usuarioCom('validacao.registrar', 'arquivos.sensiveis.visualizar');
        CaixaTurno::factory()->doOperador($operador)->create();
        $pessoa = Pessoa::factory()->create();

        $tela = Livewire::actingAs($operador)->test(AccessValidation::class)
            ->assertSee('(SIMULADOR)')
            ->call('selecionarPessoa', $pessoa->id)
            ->set('cameraPlacaId', $camera->id)
            ->call('capturarPlaca')
            ->assertSet('capturaPlacaFalha', null)
            ->assertSee('SIMULADOR · imagem sintética')
            ->assertSee('Cancela principal')
            ->assertSee('contexto=evidencia_placa', escape: false)
            ->set('placaInformada', 'sim-0a00')
            ->assertSet('placaInformada', 'SIM0A00')
            ->set('ticketNumero', '000777')
            ->call('release')
            ->assertHasNoErrors();

        $captura = CapturaPlaca::query()->sole();
        $this->assertSame('simulador', $captura->origem);
        $this->assertSame($camera->id, $captura->equipamento_id);
        $this->assertNotNull($captura->ponto_acesso_id);
        Storage::disk(PrivateFileService::DISK)->assertExists($captura->arquivo->object_key);
        $this->assertSame('evidencia_placa', $captura->arquivo->purpose);

        $historico = HistoricoAcesso::query()->sole();
        $this->assertSame('SIM0A00', $historico->placa_informada);
        $this->assertSame('conferida', $historico->placa_origem);
        $this->assertSame($captura->id, $historico->captura_placa_id);
        $this->assertSame($historico->id, $captura->fresh()->historico_acesso_id);
        $this->assertSame($tela->get('protocol'), $historico->protocol);

        $evento = AuditoriaEvento::query()->where('action', 'placa_imagem_capturada')->sole();
        $this->assertSame('simulador', $evento->context->metadata['origem']);
        $this->assertArrayNotHasKey('conteudo', $evento->context->metadata);

        $this->actingAs($operador)
            ->get(route('protected-files.show', ['arquivo' => $captura->arquivo_id, 'contexto' => 'evidencia_placa']))
            ->assertOk();
        $visualizacao = AuditoriaEvento::query()->where('action', 'visualizou_arquivo_protegido')->sole();
        $this->assertSame('validacao', $visualizacao->module);
        $this->assertSame('evidencia_placa', $visualizacao->context->metadata['contexto']);

        $this->expectException(LogicException::class);
        $captura->fresh()->update(['placa_lida' => 'ABC1D23']);
    }

    public function test_snapshot_http_resolve_endereco_e_credencial_so_no_backend(): void
    {
        $camera = $this->cameraHttp();
        Http::fake(['*' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg'])]);
        $operador = $this->usuarioCom('validacao.registrar');

        $html = Livewire::actingAs($operador)->test(AccessValidation::class)
            ->set('cameraPlacaId', $camera->id)
            ->call('capturarPlaca')
            ->assertSet('capturaPlacaFalha', null)
            ->html();

        Http::assertSent(fn (Request $r) => $r->url() === 'http://'.self::IP_TESTE.':8080/snapshot.jpg'
            && ! str_contains($r->url(), 'Senha-Camera')
            && $r->method() === 'GET');
        $this->assertStringNotContainsString(self::IP_TESTE, $html);
        $this->assertStringNotContainsString('/snapshot.jpg', $html);
        $this->assertStringNotContainsString('Senha-Camera', $html);
        $this->assertStringNotContainsString('SDV_TESTE_CAMERA_SENHA', $html);
        $this->assertSame('camera', CapturaPlaca::query()->sole()->origem);
    }

    public function test_resposta_que_nao_e_imagem_ou_camera_fora_do_ar_nao_vira_evidencia(): void
    {
        $camera = $this->cameraHttp();
        $operador = $this->usuarioCom('validacao.registrar');

        Http::fake(['*' => Http::response('<html>login</html>', 200, ['Content-Type' => 'text/html'])]);
        Livewire::actingAs($operador)->test(AccessValidation::class)
            ->set('cameraPlacaId', $camera->id)
            ->call('capturarPlaca')
            ->assertSet('capturaPlacaId', null)
            ->assertSee('A câmera não devolveu uma imagem.')
            ->assertSee('Você pode digitar a placa.');

        Cache::flush();
        Http::fake(['*' => fn () => throw new ConnectionException('cURL error 7: falha')]);
        Livewire::actingAs($operador)->test(AccessValidation::class)
            ->set('cameraPlacaId', $camera->id)
            ->call('capturarPlaca')
            ->assertSee('A câmera não respondeu.');

        $this->assertSame(0, CapturaPlaca::query()->count());
        $this->assertSame(2, IntegracaoOcorrencia::query()->where('tipo', 'captura_placa')->where('resultado', '!=', 'confirmado')->count());
        $this->assertSame(2, AuditoriaEvento::query()->where('action', 'placa_imagem_capturada')->where('result', 'falha')->count());
    }

    public function test_placa_digitada_e_contingencia_sem_camera_e_formato_e_validado(): void
    {
        $operador = $this->usuarioCom('validacao.registrar');
        $pessoa = Pessoa::factory()->create();

        Livewire::actingAs($operador)->test(AccessValidation::class)
            ->assertSee('Nenhuma câmera de placas neste posto')
            ->call('selecionarPessoa', $pessoa->id)
            ->set('placaInformada', 'AB12')
            ->set('contribution', 'no')
            ->call('savePending')
            ->assertHasErrors('placaInformada')
            ->set('placaInformada', 'abc-1234')
            ->call('savePending')
            ->assertHasNoErrors();

        $historico = HistoricoAcesso::query()->sole();
        $this->assertSame('ABC1234', $historico->placa_informada);
        $this->assertSame('manual', $historico->placa_origem);
        $this->assertNull($historico->captura_placa_id);
    }

    public function test_cadastro_recusa_endereco_publico_credencial_no_caminho_e_senha_em_texto(): void
    {
        $admin = $this->usuarioCom('integracoes.gerenciar');
        $cameras = app(CamerasPlaca::class);
        $base = ['nome' => 'Câmera', 'adaptador' => 'snapshot-http', 'endereco_rede' => self::IP_TESTE, 'caminho_snapshot' => '/snapshot.jpg'];

        $this->assertRegraViolada('endereco_publico', fn () => $cameras->cadastrar([...$base, 'endereco_rede' => '8.8.8.8'], $admin));
        $this->assertRegraViolada('caminho_com_credencial', fn () => $cameras->cadastrar([...$base, 'caminho_snapshot' => '/snap.jpg?user=admin&pwd=123'], $admin));
        $this->assertRegraViolada('caminho_invalido', fn () => $cameras->cadastrar([...$base, 'caminho_snapshot' => 'http://outra/snap.jpg'], $admin));

        $camera = $cameras->cadastrar($base, $admin);
        $this->assertRegraViolada('referencia_segredo_invalida', fn () => $cameras->definirCredencial($camera, 'SenhaEmTexto123', 'admin', $admin));
        $this->assertRegraViolada('sem_permissao', fn () => $cameras->cadastrar($base, $this->usuarioCom('validacao.registrar')));
    }

    public function test_camera_nao_ocupa_ponto_do_terminal_nem_aparece_na_tela_de_terminais(): void
    {
        $camera = $this->cameraSimulada();
        $ponto = $camera->vinculoCameraVigente->pontoAcesso;

        $this->assertRegraViolada('tipo_equipamento_invalido', fn () => $this->cadastro()->vincularAoPonto($camera, $ponto));
        $terminal = $this->novoEquipamento();
        $this->cadastro()->vincularAoPonto($terminal, $ponto);

        Livewire::actingAs($this->usuarioCom('integracoes.gerenciar'))
            ->test(EquipamentoManagement::class)
            ->assertSee($terminal->nome)
            ->assertDontSee('Câmera simulada da entrada');
    }

    public function test_camera_de_outra_implantacao_inativa_ou_simulador_proibido_nao_captura(): void
    {
        $operador = $this->usuarioCom('validacao.registrar');
        $atual = ImplantacaoContext::current();
        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        $outra = $this->cameraSimulada();
        ImplantacaoContext::setCurrentForTesting($atual);

        Livewire::actingAs($operador)->test(AccessValidation::class)
            ->set('cameraPlacaId', $outra->id)
            ->call('capturarPlaca')
            ->assertHasErrors('cameraPlacaId');

        $camera = $this->cameraSimulada();
        app(CamerasPlaca::class)->inativar($camera, 'câmera substituída', $this->usuarioCom('integracoes.gerenciar'));
        $this->assertRegraViolada('camera_indisponivel', fn () => app(CamerasPlaca::class)->capturar($camera->id, $operador));

        config()->set('integracoes.simulador_permitido', false);
        $nova = Equipamento::query()->camerasPlaca()->whereKeyNot($camera->id)->exists();
        $this->assertFalse($nova);
        $this->assertSame([], app(CamerasPlaca::class)->disponiveisPara($operador)->all());

        $this->expectException(ModelNotFoundException::class);
        app(CamerasPlaca::class)->capturar($outra->id, $operador);
    }

    public function test_tela_de_cameras_cadastra_simulador_vincula_e_testa(): void
    {
        $ponto = $this->novoPonto(['nome' => 'Entrada Norte']);
        $admin = $this->usuarioCom('integracoes.gerenciar', 'arquivos.sensiveis.visualizar');

        $tela = Livewire::actingAs($admin)->test(CameraPlacasManagement::class)
            ->set('form.nome', 'Câmera de teste')
            ->set('form.adaptador', 'simulador-camera')
            ->call('cadastrar')
            ->assertSet('feedback.variant', 'success');

        $camera = Equipamento::query()->camerasPlaca()->sole();
        $this->assertSame('placas', $camera->finalidade);

        $tela->set("pontoEscolhido.{$camera->id}", $ponto->id)
            ->call('vincular', $camera->id)
            ->assertSet('feedback.variant', 'success')
            ->call('testarCaptura', $camera->id)
            ->assertSee('Imagem SINTÉTICA do simulador')
            ->assertSee('SIMULADOR · imagem sintética');

        $this->assertSame(StatusEquipamento::Ativo, $camera->fresh()->status);

        Livewire::actingAs($this->usuarioCom('validacao.registrar'))
            ->test(CameraPlacasManagement::class)
            ->assertForbidden();
    }

    private function cameraSimulada(): Equipamento
    {
        $admin = $this->usuarioCom('integracoes.gerenciar');
        $camera = app(CamerasPlaca::class)->cadastrar(['nome' => 'Câmera simulada da entrada', 'adaptador' => 'simulador-camera'], $admin);
        app(CamerasPlaca::class)->vincularAoPonto($camera, $this->novoPonto(), $admin);

        return $camera->refresh();
    }

    private function cameraHttp(): Equipamento
    {
        $admin = $this->usuarioCom('integracoes.gerenciar');
        $cameras = app(CamerasPlaca::class);
        $camera = $cameras->cadastrar([
            'nome' => 'Câmera HTTP', 'adaptador' => 'snapshot-http', 'endereco_rede' => self::IP_TESTE,
            'porta_rede' => 8080, 'esquema' => 'http', 'caminho_snapshot' => '/snapshot.jpg',
        ], $admin);
        $cameras->definirCredencial($camera, 'env:SDV_TESTE_CAMERA_SENHA', 'operador', $admin);
        $cameras->vincularAoPonto($camera, $this->novoPonto(), $admin);

        return $camera->refresh();
    }

    private function jpeg(): string
    {
        $imagem = imagecreatetruecolor(320, 180);
        ob_start();
        imagejpeg($imagem);

        return (string) ob_get_clean();
    }
}
