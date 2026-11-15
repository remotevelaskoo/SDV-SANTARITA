<?php

namespace Tests\Feature;

use App\Acesso\Aplicacao\EnvioFacial;
use App\Livewire\AccessValidation;
use App\Models\AutorizacaoAcesso;
use App\Models\HistoricoAcesso;
use App\Models\Imovel;
use App\Models\OperacaoIntegracao;
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
        $this->operador = $this->abrirCaixa($this->usuarioCom('validacao.registrar', 'pessoas.gerenciar', 'facial.enviar'));
    }

    protected function tearDown(): void
    {
        $this->encerrarIntegracao();
        parent::tearDown();
    }

    /** Cadastro rápido até o Concluir, a pé. */
    private function novoNoConcluir(string $tipo, array $extras = [], ?User $operador = null, string $contribuicao = 'no'): Testable
    {
        $tela = Livewire::actingAs($operador ?? $this->operador)->test(AccessValidation::class)
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
            $tela->set('contribution', $contribuicao);
            if ($contribuicao === 'yes') {
                $tela->set('paymentMethod', 'dinheiro')->set('ticketNumero', '000123');
            }
            $tela->call('continuar');
        }

        return $tela->assertSet('passo', 'concluir');
    }

    /**
     * Regra do PO (14/11/2026): liberar para a facial exige caixa aberto de quem
     * libera, seja contribuição paga, não contribui ou isento. Sem caixa a
     * portaria só consulta; "Salvar sem liberar" e "Negar" seguem permitidos.
     */
    public function test_sem_caixa_aberto_liberar_entrada_e_bloqueado_em_qualquer_contribuicao(): void
    {
        $semCaixa = $this->usuarioCom('validacao.registrar', 'pessoas.gerenciar', 'facial.enviar');

        foreach (['yes', 'no', 'exempt'] as $contribuicao) {
            $this->novoNoConcluir('turista', [], $semCaixa, $contribuicao)
                ->assertSee('Abra o caixa do seu posto para liberar a entrada.')
                ->call('concluir', 'liberar')
                ->assertHasErrors('liberarEntrada')
                ->assertSet('protocol', null)
                ->assertSet('envioFacialLiberacao', null);
        }

        // Nada foi gravado: sem pessoa, sem autorização, sem histórico e nenhum pedido ao terminal.
        $this->assertSame(0, Pessoa::query()->count());
        $this->assertSame(0, AutorizacaoAcesso::query()->count());
        $this->assertSame(0, HistoricoAcesso::query()->count());
        $this->assertSame(0, OperacaoIntegracao::query()->count());
    }

    public function test_sem_caixa_aberto_salvar_sem_liberar_continua_permitido(): void
    {
        $semCaixa = $this->usuarioCom('validacao.registrar', 'pessoas.gerenciar', 'facial.enviar');

        $this->novoNoConcluir('turista', [], $semCaixa)
            ->call('concluir', 'pendente')
            ->assertHasNoErrors();

        $this->assertSame(1, Pessoa::query()->count());
        $this->assertSame('pendente', HistoricoAcesso::query()->sole()->resultado);
        $this->assertSame(0, AutorizacaoAcesso::query()->count());
    }

    public function test_envio_a_facial_sem_caixa_e_recusado_para_qualquer_perfil(): void
    {
        $terminal = $this->terminalAtivo(['nome' => 'Facial entrada']);
        $pessoa = Pessoa::factory()->create();
        // Gestor/Administrador também precisa de caixa aberto para liberar.
        $administrador = $this->usuarioCom('pessoas.gerenciar', 'facial.enviar', 'autorizacoes.gerenciar', 'integracoes.gerenciar');
        $operacoesAntes = OperacaoIntegracao::query()->count();

        $resultado = app(EnvioFacial::class)->enviar($pessoa, $terminal->id, $administrador);

        $this->assertFalse($resultado->aceito());
        $this->assertSame('caixa_fechado', $resultado->codigo);
        $this->assertSame($operacoesAntes, OperacaoIntegracao::query()->count(), 'Nenhum pedido novo ao terminal.');
        $this->assertSame(0, AutorizacaoAcesso::query()->count());
        $this->assertSame([], $this->simulador()->credenciaisGravadas($terminal->id));
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
