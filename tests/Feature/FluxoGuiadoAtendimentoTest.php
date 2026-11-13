<?php

namespace Tests\Feature;

use App\Livewire\AccessValidation;
use App\Models\AutorizacaoAcesso;
use App\Models\Catalogo;
use App\Models\CatalogoItem;
use App\Models\Contribuicao;
use App\Models\HistoricoAcesso;
use App\Models\Imovel;
use App\Models\Pessoa;
use App\Models\PessoaArquivo;
use App\Models\User;
use App\Models\Vinculo;
use App\Services\PerfilAtendimento;
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
 * Atendimento guiado da Validação (pedido do PO de 12/11/2026):
 * Dados → Foto → Placa → Pagamento → Concluir, gravando tudo só no Concluir.
 */
class FluxoGuiadoAtendimentoTest extends TestCase
{
    use MontaCenarioIntegracao, RefreshDatabase;

    private const CPF = '529.982.247-25';

    private User $operador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararIntegracao();
        Storage::fake(PrivateFileService::DISK);
        $this->operador = $this->usuarioCom('validacao.registrar', 'pessoas.gerenciar');
    }

    protected function tearDown(): void
    {
        $this->encerrarIntegracao();
        parent::tearDown();
    }

    private function foto(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('rosto.jpg', ImagensPessoaSinteticas::boa());
    }

    /** Turista novo com os dados digitados, parado na etapa Foto. */
    private function turistaNovoNaFoto(): Testable
    {
        return Livewire::actingAs($this->operador)->test(AccessValidation::class)
            ->assertSet('passo', 'dados')
            ->call('openQuickRegistration')
            ->set('quickEtapa', 'conferencia')
            ->set('quickName', 'Turista Sintético')
            ->set('quickDocument', self::CPF)
            ->set('quickAccessType', 'turista')
            ->call('continuar')
            ->assertHasNoErrors()
            ->assertSet('passo', 'foto');
    }

    public function test_turista_novo_percorre_as_cinco_etapas_e_tudo_e_gravado_so_no_concluir(): void
    {
        $tela = $this->turistaNovoNaFoto()
            ->call('continuar')
            ->assertHasErrors('fotoPessoa')
            ->assertSet('passo', 'foto')
            ->set('fotoPessoa', $this->foto())
            ->call('continuar')
            ->assertSet('passo', 'placa')
            ->call('continuar')
            ->assertHasErrors('veioDeCarro')
            ->set('veioDeCarro', true)
            ->call('continuar')
            ->assertHasErrors('placaInformada')
            ->set('placaInformada', 'abc1d23')
            ->call('continuar')
            ->assertSet('passo', 'pagamento')
            ->set('contribution', 'yes')
            ->set('paymentMethod', 'pix')
            ->call('continuar')
            ->assertHasErrors('ticketNumero')
            ->set('ticketNumero', '000123')
            ->call('continuar')
            ->assertSet('passo', 'concluir')
            ->assertSee('Turista Sintético')
            ->assertSee('ABC1D23')
            ->assertSee('Contribui R$ 25,00 · PIX · ticket 000123');

        $this->assertSame(0, Pessoa::query()->count(), 'Nada é gravado antes do Concluir.');
        $this->assertSame(0, HistoricoAcesso::query()->count());

        $tela->call('concluir', 'pendente')->assertHasNoErrors();

        $pessoa = Pessoa::query()->sole();
        $this->assertSame('Turista Sintético', $pessoa->nome);
        $this->assertNotNull($pessoa->fotoVigente());
        $historico = HistoricoAcesso::query()->sole();
        $this->assertSame('pendente', $historico->resultado);
        $this->assertSame('ABC1D23', $historico->placa_informada);
        $this->assertSame($pessoa->id, $historico->pessoa_id);
        $this->assertSame('000123', Contribuicao::query()->sole()->ticket_numero);
        $this->assertNotNull($tela->get('protocol'));
    }

    public function test_cancelar_no_meio_nao_deixa_cadastro_pela_metade(): void
    {
        $this->turistaNovoNaFoto()
            ->set('fotoPessoa', $this->foto())
            ->call('continuar')
            ->assertSet('passo', 'placa')
            ->call('startNewValidation')
            ->assertSet('passo', 'dados')
            ->assertSet('quickRegistrationOpen', false)
            ->assertSet('veioDeCarro', null);

        $this->assertSame(0, Pessoa::query()->count());
        $this->assertSame(0, PessoaArquivo::query()->count());
        $this->assertSame(0, HistoricoAcesso::query()->count());
    }

    public function test_concluir_confere_tudo_de_novo_e_volta_para_a_etapa_com_problema(): void
    {
        // Pula as etapas mexendo no estado: o Concluir não aceita sem foto.
        $this->turistaNovoNaFoto()
            ->set('passo', 'concluir')
            ->call('concluir', 'pendente')
            ->assertHasErrors('fotoPessoa')
            ->assertSet('passo', 'foto');

        $this->assertSame(0, Pessoa::query()->count());
        $this->assertSame(0, HistoricoAcesso::query()->count());
    }

    /** Pedido do PO de 13/11/2026: quem já tem foto também passa pela etapa Foto, que já vem pronta. */
    public function test_pessoa_achada_com_foto_e_vinculo_passa_pela_foto_com_tipo_travado(): void
    {
        $pessoa = Pessoa::factory()->create(['nome' => 'Mara Moradora']);
        app(PrivateFileService::class)->storePessoaFoto($pessoa, $this->foto());
        Vinculo::factory()->for($pessoa, 'pessoa')->for(Imovel::factory()->create(), 'imovel')->create(['ended_at' => null, 'tipo' => 'morador']);

        $tela = Livewire::actingAs($this->operador)->test(AccessValidation::class)
            ->call('continuar')
            ->assertHasErrors('busca')
            ->call('selecionarPessoa', $pessoa->id)
            ->assertDontSee('Foto (não se aplica)')
            ->assertSee('Pagamento (não se aplica)')
            ->assertSee('Definido pelo vínculo com o imóvel')
            ->assertDontSee('Escolha o tipo')
            ->call('continuar')
            ->assertSet('passo', 'foto')
            ->assertSee('Tirar nova foto')
            ->call('continuar')
            ->assertSet('passo', 'placa')
            ->set('veioDeCarro', false)
            ->call('continuar')
            ->assertSet('passo', 'concluir')
            ->assertSee('Veio a pé')
            ->call('concluir', 'liberar')
            ->assertHasNoErrors();

        $this->assertSame(['dados', 'foto', 'placa', 'concluir'], array_keys($tela->instance()->passosDoAtendimento()));
        $this->assertSame('liberado', HistoricoAcesso::query()->sole()->resultado);
        $this->assertSame(0, Contribuicao::query()->count());
        $this->assertSame(0, AutorizacaoAcesso::query()->count(), 'Quem tem vínculo não ganha autorização pelo tipo.');
        $this->assertSame(1, PessoaArquivo::query()->where('pessoa_id', $pessoa->id)->count(), 'Sem nova foto, a do cadastro fica.');
    }

    public function test_nova_foto_de_quem_ja_tem_foto_substitui_no_concluir(): void
    {
        $pessoa = Pessoa::factory()->create();
        app(PrivateFileService::class)->storePessoaFoto($pessoa, $this->foto());
        Vinculo::factory()->for($pessoa, 'pessoa')->for(Imovel::factory()->create(), 'imovel')->create(['ended_at' => null, 'tipo' => 'morador']);

        Livewire::actingAs($this->operador)->test(AccessValidation::class)
            ->call('selecionarPessoa', $pessoa->id)
            ->call('continuar')
            ->set('fotoIdentificacao', $this->foto())
            ->call('continuar')
            ->set('veioDeCarro', false)
            ->call('continuar')
            ->call('concluir', 'pendente')
            ->assertHasNoErrors();

        $this->assertSame(2, PessoaArquivo::query()->where('pessoa_id', $pessoa->id)->count(), 'A anterior fica no histórico.');
        $this->assertSame(1, PessoaArquivo::query()->where('pessoa_id', $pessoa->id)->where('is_current', true)->count());
    }

    public function test_pessoa_sem_vinculo_escolhe_turista_e_liberar_grava_24_horas(): void
    {
        $pessoa = Pessoa::factory()->create();
        app(PrivateFileService::class)->storePessoaFoto($pessoa, $this->foto());

        $tela = Livewire::actingAs($this->operador)->test(AccessValidation::class)
            ->call('selecionarPessoa', $pessoa->id)
            ->assertSee('Esta pessoa não tem vínculo')
            ->call('continuar')
            ->assertHasErrors('tipoAtendimento')
            ->assertSet('passo', 'dados')
            ->set('tipoAtendimento', 'turista')
            ->call('continuar')
            ->assertSet('passo', 'foto')
            ->call('continuar')
            ->set('veioDeCarro', false)
            ->call('continuar')
            ->assertSet('passo', 'pagamento')
            ->set('contribution', 'no')
            ->call('continuar')
            ->assertSee('Turista por 24 h')
            ->call('concluir', 'liberar')
            ->assertHasNoErrors();

        $autorizacao = AutorizacaoAcesso::query()->sole();
        $this->assertSame(AutorizacaoAcesso::TIPO_TURISTA, $autorizacao->tipo);
        $this->assertNull($autorizacao->imovel_id);
        $this->assertEqualsWithDelta(24 * 60, $autorizacao->inicio->diffInMinutes($autorizacao->fim), 1);
        $this->assertSame('Turista', app(PerfilAtendimento::class)->daPessoa($pessoa->id, now())['perfil']);
        $this->assertTrue($tela->instance()->tipoDoCadastro()['travado'], 'Depois de liberar, o tipo vem do cadastro.');
    }

    public function test_salvar_sem_liberar_nao_grava_o_tipo(): void
    {
        $pessoa = Pessoa::factory()->create();
        app(PrivateFileService::class)->storePessoaFoto($pessoa, $this->foto());

        Livewire::actingAs($this->operador)->test(AccessValidation::class)
            ->call('selecionarPessoa', $pessoa->id)
            ->set('tipoAtendimento', 'prestador')
            ->call('continuar')
            ->call('continuar')
            ->set('veioDeCarro', false)
            ->call('continuar')
            ->call('concluir', 'pendente')
            ->assertHasNoErrors();

        $this->assertSame(0, AutorizacaoAcesso::query()->count());
        $this->assertSame('pendente', HistoricoAcesso::query()->sole()->resultado);
    }

    public function test_visitante_sem_vinculo_tem_prazo_do_porteiro_imovel_e_responsavel(): void
    {
        $pessoa = Pessoa::factory()->create();
        app(PrivateFileService::class)->storePessoaFoto($pessoa, $this->foto());
        $imovel = Imovel::factory()->create();
        $fim = now()->addDays(3)->setSecond(0);

        Livewire::actingAs($this->operador)->test(AccessValidation::class)
            ->call('selecionarPessoa', $pessoa->id)
            ->set('tipoAtendimento', 'visitante')
            ->set('validoAte', now()->addDays(31)->format('Y-m-d\TH:i'))
            ->call('continuar')
            ->assertHasErrors(['validoAte', 'imovelAtendimento', 'responsavelAtendimento'])
            ->set('validoAte', $fim->format('Y-m-d\TH:i'))
            ->set('imovelAtendimento', strtolower($imovel->codigo))
            ->set('responsavelAtendimento', 'Ana do 102')
            ->call('continuar')
            ->assertHasNoErrors()
            ->assertSet('passo', 'foto')
            ->call('continuar')
            ->set('veioDeCarro', false)
            ->call('continuar')
            ->assertSet('passo', 'concluir')
            ->call('concluir', 'liberar')
            ->assertHasNoErrors();

        $autorizacao = AutorizacaoAcesso::query()->sole();
        $this->assertSame('visitante', $autorizacao->tipo);
        $this->assertSame($imovel->id, $autorizacao->imovel_id);
        $this->assertSame('Ana do 102', $autorizacao->responsavel);
        $this->assertSame($fim->format('Y-m-d H:i'), $autorizacao->fim->format('Y-m-d H:i'));
        $this->assertSame(0, Contribuicao::query()->count(), 'Só turista contribui.');
        $this->assertSame('Visitante', app(PerfilAtendimento::class)->daPessoa($pessoa->id, now())['perfil']);
    }

    public function test_voltar_e_barra_de_etapas_so_levam_para_tras(): void
    {
        $this->turistaNovoNaFoto()
            ->call('irParaPasso', 'concluir')
            ->assertSet('passo', 'foto')
            ->call('irParaPasso', 'dados')
            ->assertSet('passo', 'dados')
            ->call('continuar')
            ->assertSet('passo', 'foto')
            ->call('voltar')
            ->assertSet('passo', 'dados');
    }

    public function test_negar_exige_motivo_e_grava_cadastro_e_negativa_juntos(): void
    {
        $tela = $this->turistaNovoNaFoto()
            ->set('fotoPessoa', $this->foto())
            ->call('continuar')
            ->set('veioDeCarro', false)
            ->call('continuar')
            ->set('contribution', 'no')
            ->call('continuar')
            ->assertSet('passo', 'concluir')
            ->set('denialReason', '')
            ->call('concluir', 'negar')
            ->assertHasErrors('denialReason');
        $this->assertSame(0, Pessoa::query()->count(), 'Negativa sem motivo não grava o cadastro.');

        $catalogo = Catalogo::factory()->create(['chave' => 'motivos_negativa']);
        CatalogoItem::factory()->create(['catalogo_id' => $catalogo->id, 'codigo' => 'sem_autorizacao', 'rotulo' => 'Sem autorização', 'status' => 'ativo']);

        $tela->set('denialReason', 'sem_autorizacao')
            ->call('concluir', 'negar')
            ->assertHasNoErrors();

        $this->assertSame(1, Pessoa::query()->count());
        $this->assertSame('negado', HistoricoAcesso::query()->sole()->resultado);
    }
}
