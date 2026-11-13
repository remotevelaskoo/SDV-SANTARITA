<?php

namespace Tests\Feature;

use App\Livewire\AccessValidation;
use App\Models\AutorizacaoAcesso;
use App\Models\HistoricoAcesso;
use App\Models\Imovel;
use App\Models\Pessoa;
use App\Models\User;
use App\Models\Vinculo;
use App\Services\PrivateFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\Support\ImagensPessoaSinteticas;
use Tests\TestCase;

/**
 * Regra do PO (13/11/2026) para os três botões do Concluir:
 * - Liberar entrada: salva, grava a autorização com o tempo do perfil e envia ao
 *   terminal facial (a pessoa passa na facial; o SDV não abre nada);
 * - Salvar sem liberar: só salva, sem autorização e sem facial;
 * - Negar: registra a negativa.
 * Vale também para a pessoa nova do cadastro rápido.
 */
class LiberarEntradaFacialTest extends TestCase
{
    use MontaCenarioIntegracao, RefreshDatabase;

    private User $operador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararIntegracao();
        Storage::fake(PrivateFileService::DISK);
        $this->operador = $this->usuarioCom('validacao.registrar', 'pessoas.gerenciar', 'facial.enviar');
    }

    protected function tearDown(): void
    {
        $this->encerrarIntegracao();
        parent::tearDown();
    }

    /** Cadastro rápido até o Concluir, a pé. */
    private function novoNoConcluir(string $tipo, array $extras = []): Testable
    {
        $tela = Livewire::actingAs($this->operador)->test(AccessValidation::class)
            ->call('openQuickRegistration')
            ->set('quickEtapa', 'conferencia')
            ->set('quickName', 'Pessoa Sintética')
            ->set('quickDocument', '529.982.247-25')
            ->set('quickAccessType', $tipo);
        foreach ($extras as $campo => $valor) {
            $tela->set($campo, $valor);
        }

        $tela->call('continuar')->assertHasNoErrors()
            ->set('fotoPessoa', UploadedFile::fake()->createWithContent('rosto.jpg', ImagensPessoaSinteticas::boa()))
            ->call('continuar')
            ->set('veioDeCarro', false)
            ->call('continuar');
        if ($tipo === 'turista') {
            $tela->set('contribution', 'no')->call('continuar');
        }

        return $tela->assertSet('passo', 'concluir');
    }

    public function test_turista_novo_liberado_ganha_24_horas_e_vai_ao_terminal_facial(): void
    {
        $this->novoNoConcluir('turista')
            ->assertSee('Turista por 24 h, gravada e enviada ao terminal facial')
            ->call('concluir', 'liberar')
            ->assertHasNoErrors()
            ->assertSet('feedback.title', 'Entrada liberada')
            ->assertSee('Envio ao terminal facial pendente')
            ->assertSee('Turista: acesso até');

        $autorizacao = AutorizacaoAcesso::query()->sole();
        $this->assertSame(AutorizacaoAcesso::TIPO_TURISTA, $autorizacao->tipo);
        $this->assertEqualsWithDelta(24 * 60, $autorizacao->inicio->diffInMinutes($autorizacao->fim), 1);
        $this->assertSame('liberado', HistoricoAcesso::query()->sole()->resultado);
    }

    public function test_prestador_novo_liberado_ganha_o_prazo_escolhido(): void
    {
        $fim = now()->addDays(3)->startOfMinute();

        $this->novoNoConcluir('prestador', ['validoAte' => $fim->format('Y-m-d\TH:i')])
            ->assertSee('Prestador até '.$fim->format('d/m/Y H:i'))
            ->call('concluir', 'liberar')
            ->assertHasNoErrors()
            ->assertSet('feedback.title', 'Entrada liberada');

        $autorizacao = AutorizacaoAcesso::query()->sole();
        $this->assertSame('prestador', $autorizacao->tipo);
        $this->assertSame($fim->format('Y-m-d H:i'), $autorizacao->fim->format('Y-m-d H:i'));
        $this->assertSame('liberado', HistoricoAcesso::query()->sole()->resultado);
    }

    public function test_salvar_sem_liberar_so_salva_sem_autorizacao_e_sem_facial(): void
    {
        $this->novoNoConcluir('turista')
            ->call('concluir', 'pendente')
            ->assertHasNoErrors()
            ->assertSet('envioFacialLiberacao', null);

        $this->assertSame(1, Pessoa::query()->count());
        $this->assertSame(0, AutorizacaoAcesso::query()->count());
        $this->assertSame('pendente', HistoricoAcesso::query()->sole()->resultado);
    }

    public function test_morador_com_vinculo_liberado_vai_ao_facial_pelo_vinculo(): void
    {
        $imovel = Imovel::factory()->create(['codigo' => 'SRA-A-102']);
        $pessoa = Pessoa::factory()->create();
        Vinculo::query()->create(['pessoa_id' => $pessoa->id, 'imovel_id' => $imovel->id, 'tipo' => 'morador', 'papel' => 'titular', 'status' => 'ativo', 'origem' => 'cadastro_manual', 'started_at' => now()->subYear(), 'versao' => 1]);

        Livewire::actingAs($this->operador)->test(AccessValidation::class)
            ->call('selecionarPessoa', $pessoa->id)
            ->set('passo', 'concluir')->set('veioDeCarro', false)
            ->set('fotoIdentificacao', UploadedFile::fake()->createWithContent('rosto.jpg', ImagensPessoaSinteticas::boa()))
            ->call('concluir', 'liberar')
            ->assertHasNoErrors()
            ->assertSet('feedback.title', 'Entrada liberada')
            ->assertSee('Acesso pelo vínculo com o imóvel.');

        $this->assertSame(0, AutorizacaoAcesso::query()->count(), 'Morador entra pelo vínculo; nenhuma autorização nova.');
    }

    public function test_morador_novo_sem_imovel_nao_e_liberado(): void
    {
        $this->novoNoConcluir('morador')
            ->assertSee('Morador sem imóvel: sem autorização.')
            ->call('concluir', 'liberar')
            ->assertSet('feedback.title', 'Liberação não realizada')
            ->assertSet('envioFacialLiberacao', null);

        $this->assertSame('pendente', HistoricoAcesso::query()->sole()->resultado);
    }
}
