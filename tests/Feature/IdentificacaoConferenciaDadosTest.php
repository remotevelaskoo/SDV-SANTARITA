<?php

namespace Tests\Feature;

use App\Livewire\AccessValidation;
use App\Models\AuditoriaEvento;
use App\Models\Pessoa;
use App\Models\PessoaContato;
use App\Models\PessoaDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\TestCase;

/**
 * Identificação da pessoa na validação: dados abertos para o porteiro
 * conferir e corrigir. Só dados sintéticos.
 */
class IdentificacaoConferenciaDadosTest extends TestCase
{
    use MontaCenarioIntegracao, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararIntegracao();
    }

    protected function tearDown(): void
    {
        $this->encerrarIntegracao();
        parent::tearDown();
    }

    private function pessoa(): Pessoa
    {
        $pessoa = Pessoa::factory()->create(['nome' => 'Pessoa Sintetica Errada', 'data_nascimento' => '1990-05-12']);
        PessoaDocumento::query()->create([
            'pessoa_id' => $pessoa->id, 'tipo' => 'cpf', 'pais' => 'BR',
            'valor_normalizado' => '52998224725', 'valor_apresentacao' => '529.982.247-25',
            'status' => 'ativo', 'started_at' => now(),
        ]);
        PessoaContato::query()->create([
            'pessoa_id' => $pessoa->id, 'tipo' => 'telefone', 'valor' => '(11) 90000-0001',
            'principal' => true, 'verificado' => false, 'started_at' => now(),
        ]);

        return $pessoa;
    }

    private function tela(Pessoa $pessoa, string ...$permissoes): Testable
    {
        return Livewire::actingAs($this->usuarioCom('validacao.registrar', ...$permissoes))
            ->test(AccessValidation::class)
            ->call('selecionarPessoa', $pessoa->id);
    }

    public function test_mostra_os_dados_para_conferencia_com_cpf_mascarado(): void
    {
        $this->tela($this->pessoa())
            ->assertSee('Dados para conferência')
            ->assertSee('Pessoa Sintetica Errada')
            ->assertSee('12/05/1990')
            ->assertSee('(11) 90000-0001')
            ->assertDontSee('529.982.247-25')
            ->assertDontSee('Editar dados');
    }

    public function test_porteiro_corrige_nome_cpf_e_telefone_com_auditoria(): void
    {
        $pessoa = $this->pessoa();

        $this->tela($pessoa, 'pessoas.gerenciar')
            ->call('editarIdentificacao')
            ->assertSet('identNome', 'Pessoa Sintetica Errada')
            ->assertSet('identCpf', '529.982.247-25')
            ->set('identNome', 'Pessoa Sintetica Certa')
            ->set('identCpf', '111.444.777-35')
            ->set('identTelefone', '(11) 90000-0002')
            ->call('salvarIdentificacao')
            ->assertHasNoErrors()
            ->assertSet('editandoIdentificacao', false)
            ->assertSee('Dados corrigidos')
            ->assertSee('Pessoa Sintetica Certa')
            ->assertSee('(11) 90000-0002');

        $pessoa->refresh();
        $this->assertSame('Pessoa Sintetica Certa', $pessoa->nome);
        $this->assertSame(2, $pessoa->versao);

        $antigo = PessoaDocumento::query()->where('valor_normalizado', '52998224725')->sole();
        $this->assertNotNull($antigo->ended_at, 'CPF anterior é encerrado, não apagado');
        $this->assertNull(PessoaDocumento::query()->where('valor_normalizado', '11144477735')->sole()->ended_at);
        $this->assertSame(2, PessoaContato::query()->where('pessoa_id', $pessoa->id)->where('tipo', 'telefone')->count());

        $evento = AuditoriaEvento::query()->where('action', 'pessoa_atualizada')->where('entity_id', $pessoa->id)->sole();
        $this->assertEqualsCanonicalizing(['nome', 'cpf', 'telefone'], $evento->context->metadata['campos']);
        $this->assertStringNotContainsString('Certa', json_encode($evento->context->metadata));
    }

    public function test_cpf_de_outro_cadastro_e_recusado(): void
    {
        $pessoa = $this->pessoa();
        $outra = Pessoa::factory()->create();
        PessoaDocumento::query()->create([
            'pessoa_id' => $outra->id, 'tipo' => 'cpf', 'pais' => 'BR',
            'valor_normalizado' => '11144477735', 'valor_apresentacao' => '111.444.777-35',
            'status' => 'ativo', 'started_at' => now(),
        ]);

        $this->tela($pessoa, 'pessoas.gerenciar')
            ->call('editarIdentificacao')
            ->set('identCpf', '111.444.777-35')
            ->call('salvarIdentificacao')
            ->assertHasErrors('identCpf')
            ->assertSet('editandoIdentificacao', true);

        $this->assertSame(1, $pessoa->fresh()->versao);
    }

    public function test_validacoes_de_cpf_e_telefone(): void
    {
        $this->tela($this->pessoa(), 'pessoas.gerenciar')
            ->call('editarIdentificacao')
            ->set('identCpf', '123')
            ->call('salvarIdentificacao')
            ->assertHasErrors('identCpf')
            ->set('identCpf', '529.982.247-25')
            ->set('identTelefone', '1234')
            ->call('salvarIdentificacao')
            ->assertHasErrors('identTelefone');
    }

    public function test_edicao_concorrente_e_recusada(): void
    {
        $pessoa = $this->pessoa();

        $tela = $this->tela($pessoa, 'pessoas.gerenciar')->call('editarIdentificacao');
        $pessoa->forceFill(['versao' => 2])->save();

        $tela->set('identNome', 'Outro Nome Sintetico')
            ->call('salvarIdentificacao')
            ->assertHasErrors('identNome');

        $this->assertSame('Pessoa Sintetica Errada', $pessoa->fresh()->nome);
    }

    public function test_cpf_ditado_na_correcao(): void
    {
        $tela = $this->tela($this->pessoa(), 'pessoas.gerenciar')
            ->call('editarIdentificacao')
            ->assertSee('Ditar CPF');

        $tela->call('preencherCpfIdentificacaoPorVoz', '111')->assertSet('identCpf', '529.982.247-25');
        $tela->call('preencherCpfIdentificacaoPorVoz', '11144477735')->assertSet('identCpf', '111.444.777-35');
    }

    public function test_cpf_ditado_fora_da_edicao_e_recusado(): void
    {
        $this->tela($this->pessoa(), 'pessoas.gerenciar')
            ->call('preencherCpfIdentificacaoPorVoz', '11144477735')
            ->assertForbidden();
    }

    public function test_sem_permissao_nao_edita(): void
    {
        $this->tela($this->pessoa())
            ->call('editarIdentificacao')
            ->assertForbidden();
    }
}
