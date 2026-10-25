<?php

namespace Tests\Feature\Aj006;

use App\Livewire\AccessValidation;
use App\Models\AuditoriaEvento;
use App\Models\HistoricoAcesso;
use App\Models\Imovel;
use App\Models\Implantacao;
use App\Models\Pessoa;
use App\Models\PessoaContato;
use App\Models\PessoaDocumento;
use App\Models\PreRegistration;
use App\Models\Vinculo;
use App\Services\BuscaPessoasValidacao;
use App\Services\PrivateFileService;
use App\Support\ImplantacaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\OperadorComPermissoes;
use Tests\TestCase;

/** AJ-006, fatia 1 — busca e identificação na Validação de Entrada. */
class BuscaIdentificacaoValidacaoTest extends TestCase
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

    public function test_a_tela_nao_seleciona_ninguem_automaticamente(): void
    {
        $this->pessoaComVinculo('Marcos Vinicius da Silva', '123.456.789-09');

        Livewire::actingAs($this->operador('validacao.registrar'))
            ->test(AccessValidation::class)
            ->assertSet('currentPessoaId', null)
            ->assertSet('currentPerson.selected', false)
            ->assertDontSee('Marcos Vinicius da Silva')
            ->assertSee('Nenhuma pessoa é selecionada automaticamente');
    }

    public function test_busca_por_nome_parcial_ignora_acentos_e_caixa(): void
    {
        $this->pessoaComVinculo('Ana Lúcia Gonçalves', '111.444.777-35');
        $this->pessoaComVinculo('Bruno Teixeira', '529.982.247-25');

        $resultado = app(BuscaPessoasValidacao::class)->buscar('lucia GONCAL', false);

        $this->assertCount(1, $resultado['resultados']);
        $this->assertSame('Ana Lúcia Gonçalves', $resultado['resultados'][0]['nome']);
        $this->assertSame(['Nome'], $resultado['resultados'][0]['motivos']);
        $this->assertSame('***.***.777-**', $resultado['resultados'][0]['documento']);
    }

    public function test_busca_por_cpf_com_ou_sem_mascara_telefone_protocolo_e_imovel(): void
    {
        [$pessoa, $imovel] = $this->pessoaComVinculo('Carla Mendes', '390.533.447-05', 'SRA-B-201');
        PessoaContato::factory()->for($pessoa)->create(['tipo' => 'telefone', 'valor' => '(12) 99876-5432']);
        HistoricoAcesso::factory()->create(['pessoa_id' => $pessoa->id, 'protocol' => 'SRA-20261025-ABC123']);
        PreRegistration::factory()->create(['document' => '390.533.447-05', 'protocol' => 'PRE-SRA-XY1234']);

        $busca = app(BuscaPessoasValidacao::class);

        foreach (['39053344705', '390.533.447-05', '(12) 99876-5432', '998765432', 'SRA-20261025-ABC123', 'pre-sra-xy1234', 'sra-b-201'] as $termo) {
            $ids = array_column($busca->buscar($termo, false)['resultados'], 'id');
            $this->assertSame([$pessoa->id], $ids, "termo {$termo}");
        }

        // Documento parcial não lista ninguém: evita enumeração de CPF.
        $this->assertSame([], $busca->buscar('390533', false)['resultados']);
        $this->assertSame($imovel->label(), $busca->buscar('sra-b-201', false)['resultados'][0]['destino']);
    }

    public function test_busca_fica_restrita_a_implantacao_ativa(): void
    {
        $outra = Implantacao::factory()->create();
        ImplantacaoContext::setCurrentForTesting($outra);
        $this->pessoaComVinculo('Diego Restrito', '153.509.460-56');

        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        $this->pessoaComVinculo('Diego Local', '867.140.380-20');

        $nomes = array_column(app(BuscaPessoasValidacao::class)->buscar('diego', false)['resultados'], 'nome');
        $this->assertSame(['Diego Local'], $nomes);
        $this->assertSame([], app(BuscaPessoasValidacao::class)->buscar('15350946056', false)['resultados']);
    }

    public function test_resultado_tem_limite_e_avisa_quando_ha_mais(): void
    {
        foreach (range(1, BuscaPessoasValidacao::LIMITE_PADRAO + 2) as $i) {
            Pessoa::factory()->create(['nome' => "Silva Repetido {$i}"]);
        }

        $resultado = app(BuscaPessoasValidacao::class)->buscar('silva', false);

        $this->assertCount(BuscaPessoasValidacao::LIMITE_PADRAO, $resultado['resultados']);
        $this->assertTrue($resultado['mais']);
    }

    public function test_resultados_ambiguos_nao_sao_selecionados_e_a_selecao_carrega_a_pessoa(): void
    {
        [$escolhida] = $this->pessoaComVinculo('Fernanda Souza', '714.602.380-01');
        $this->pessoaComVinculo('Fernanda Lima', '248.438.034-80');

        Livewire::actingAs($this->operador('validacao.registrar'))
            ->test(AccessValidation::class)
            ->set('busca', 'fernanda')
            ->assertSee('2 pessoas encontradas')
            ->assertSet('currentPessoaId', null)
            ->call('selecionarPessoa', $escolhida->id)
            ->assertSet('currentPessoaId', $escolhida->id)
            ->assertSet('busca', '')
            ->assertSet('currentPerson.name', 'Fernanda Souza')
            ->assertSet('currentPerson.document', '***.***.380-**')
            ->assertSee('Trocar pessoa')
            ->call('trocarPessoa')
            ->assertSet('currentPessoaId', null);

        $this->assertTrue(AuditoriaEvento::query()->where('action', 'selecionou_pessoa')->where('entity_id', $escolhida->id)->exists());
    }

    public function test_id_de_outra_implantacao_nao_pode_ser_selecionado_nem_forjado(): void
    {
        $atual = ImplantacaoContext::current();
        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        [$estranha] = $this->pessoaComVinculo('Pessoa de Fora', '320.170.770-30');
        ImplantacaoContext::setCurrentForTesting($atual);

        $teste = Livewire::actingAs($this->operador('validacao.registrar'))->test(AccessValidation::class);

        $teste->call('selecionarPessoa', $estranha->id)->assertStatus(404);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::actingAs($this->operador('validacao.registrar'))->test(AccessValidation::class)->set('currentPessoaId', $estranha->id);
    }

    public function test_foto_protegida_aparece_ao_lado_do_nome_e_avatar_neutro_sem_foto(): void
    {
        [$comFoto] = $this->pessoaComVinculo('Gabriel Fotografado', '475.627.810-80');
        [$semFoto] = $this->pessoaComVinculo('Helena Sem Foto', '982.736.430-81');
        $arquivo = app(PrivateFileService::class)->storePessoaFoto($comFoto, UploadedFile::fake()->image('rosto.jpg', 300, 300));
        $operador = $this->operador('validacao.registrar', 'arquivos.sensiveis.visualizar');

        Livewire::actingAs($operador)
            ->test(AccessValidation::class)
            ->call('selecionarPessoa', $comFoto->id)
            ->assertSet('currentPerson.photo_state', 'disponivel')
            ->assertSet('currentPerson.photo_origin', 'Foto do cadastro')
            ->assertSee(route('protected-files.show', ['arquivo' => $arquivo, 'contexto' => 'validacao']), escape: false)
            ->call('trocarPessoa')
            ->call('selecionarPessoa', $semFoto->id)
            ->assertSet('currentPerson.photo_state', 'sem_foto')
            ->assertSee('Sem foto cadastrada');

        // Sem permissão de imagem: avatar neutro e nenhuma URL protegida no HTML.
        Livewire::actingAs($this->operador('validacao.registrar'))
            ->test(AccessValidation::class)
            ->call('selecionarPessoa', $comFoto->id)
            ->assertSet('currentPerson.photo_state', 'sem_permissao')
            ->assertDontSee(route('protected-files.show', $arquivo), escape: false);

        $this->actingAs($operador)
            ->get(route('protected-files.show', ['arquivo' => $arquivo, 'contexto' => 'validacao']))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        $evento = AuditoriaEvento::query()->where('action', 'visualizou_arquivo_protegido')->where('entity_id', $arquivo->id)->latest('occurred_at')->firstOrFail();
        $this->assertSame('pessoas', $evento->module);
        $this->assertSame('validacao', $evento->context->metadata['contexto']);
    }

    public function test_foto_cai_para_a_selfie_do_pre_cadastro_aprovado(): void
    {
        [$pessoa] = $this->pessoaComVinculo('Igor Pre Cadastrado', '123.456.789-09');
        $pre = PreRegistration::factory()->withStatus('aprovado')->create(['document' => '123.456.789-09']);
        app(PrivateFileService::class)->storePreRegistrationFiles($pre, ['selfie' => UploadedFile::fake()->image('selfie.jpg')]);

        $resultado = app(BuscaPessoasValidacao::class)->buscar('igor', true)['resultados'][0];

        $this->assertSame('disponivel', $resultado['foto_estado']);
        $this->assertStringContainsString('contexto=busca_validacao', $resultado['foto_url']);
    }

    public function test_cadastro_rapido_e_oferecido_quando_ninguem_e_encontrado_e_aproveita_o_termo(): void
    {
        Livewire::actingAs($this->operador('validacao.registrar'))
            ->test(AccessValidation::class)
            ->set('busca', 'Joana Inexistente')
            ->assertSee('Nenhuma pessoa localizada')
            ->call('openQuickRegistration')
            ->assertSet('quickRegistrationOpen', true)
            ->assertSet('quickName', 'Joana Inexistente');
    }

    public function test_revelar_documento_continua_auditado_apos_a_selecao(): void
    {
        [$pessoa] = $this->pessoaComVinculo('Karina Revelada', '935.411.347-80');

        Livewire::actingAs($this->operador('validacao.registrar', 'dados-sensiveis.revelar'))
            ->test(AccessValidation::class)
            ->call('selecionarPessoa', $pessoa->id)
            ->call('toggleDocumentReveal')
            ->assertSet('currentPerson.document', '935.411.347-80');

        $this->assertTrue(AuditoriaEvento::query()->where('action', 'revelou_dado_sensivel')->where('entity_id', $pessoa->id)->exists());
    }

    public function test_nome_de_busca_acompanha_alteracoes_do_nome(): void
    {
        $pessoa = Pessoa::factory()->create(['nome' => 'Lúcio Antigo']);
        $this->assertSame('lucio antigo', $pessoa->fresh()->nome_busca);

        $pessoa->update(['nome_social' => 'Lu Novo']);
        $this->assertSame('lucio antigo lu novo', $pessoa->fresh()->nome_busca);
    }

    /** @return array{Pessoa, Imovel} */
    private function pessoaComVinculo(string $nome, string $cpf, ?string $codigoImovel = null): array
    {
        $imovel = Imovel::factory()->create($codigoImovel ? ['codigo' => $codigoImovel] : []);
        $pessoa = Pessoa::factory()->create(['nome' => $nome]);
        PessoaDocumento::factory()->for($pessoa)->create([
            'tipo' => 'cpf',
            'valor_normalizado' => preg_replace('/\D/', '', $cpf),
            'valor_apresentacao' => $cpf,
            'status' => 'ativo',
            'ended_at' => null,
        ]);
        Vinculo::factory()->for($pessoa, 'pessoa')->for($imovel, 'imovel')->create(['status' => 'ativo', 'ended_at' => null]);

        return [$pessoa, $imovel];
    }
}
