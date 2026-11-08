<?php

namespace Tests\Feature\Aj010;

use App\Documentos\Dominio\ResultadoOcr;
use App\Documentos\Infra\OcrSimulador;
use App\Livewire\AccessValidation;
use App\Models\AutorizacaoAcesso;
use App\Models\Imovel;
use App\Models\OperacaoIntegracao as Operacao;
use App\Models\Pessoa;
use App\Models\PessoaArquivo;
use App\Models\PessoaContato;
use App\Models\PessoaDocumento;
use App\Models\User;
use App\Models\Vinculo;
use App\Services\PrivateFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\Support\ImagensDocumentoSinteticas;
use Tests\Support\ImagensPessoaSinteticas;
use Tests\TestCase;

/**
 * AJ-010 — cadastro rápido em etapas: documento, conferência e foto atual da
 * pessoa, sem sair da validação. Somente imagens sintéticas e OCR simulado.
 */
class CadastroRapidoFotoTest extends TestCase
{
    use MontaCenarioIntegracao, RefreshDatabase;

    private const CPF = '529.982.247-25';

    private User $operador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararIntegracao();
        Storage::fake(PrivateFileService::DISK);
        $this->operador = $this->usuarioCom('validacao.registrar', 'pessoas.gerenciar', 'documentos.capturar');
        Imovel::factory()->create(['codigo' => 'SRA-A-102']);
    }

    protected function tearDown(): void
    {
        $this->encerrarIntegracao();
        parent::tearDown();
    }

    private function ocrResponde(array $campos): void
    {
        app(OcrSimulador::class)->responderCom(new ResultadoOcr(true, $campos));
    }

    private function ocrComCnh(): void
    {
        $this->ocrResponde([
            'nome' => ['valor' => 'Pessoa Sintetica', 'confianca' => 0.99],
            'cpf' => ['valor' => self::CPF, 'confianca' => 0.99],
            'data_nascimento' => ['valor' => '1990-05-12', 'confianca' => 0.99],
            'numero_documento' => ['valor' => '01234567890', 'confianca' => 0.99],
        ]);
    }

    private function imagem(string $conteudo, string $nome): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nome, $conteudo);
    }

    /** Atendimento em curso com contribuição, ticket, placa e observação. */
    private function atendimento(?User $operador = null): Testable
    {
        return Livewire::actingAs($operador ?? $this->operador)->test(AccessValidation::class)
            ->set('contribution', 'yes')
            ->set('paymentMethod', 'pix')
            ->set('ticketNumero', '000777')
            ->set('placaInformada', 'ABC1D23')
            ->set('notes', 'Observação do atendimento.');
    }

    private function conferido(Testable $tela): Testable
    {
        return $tela->set('quickPhone', '(12) 99999-1234')
            ->set('quickAccessType', 'visitante')
            ->set('quickResponsible', 'Responsável Sintético')
            ->set('quickPropertyCode', 'SRA-A-102');
    }

    public function test_fluxo_completo_le_o_documento_sozinho_confere_fotografa_e_salva_preservando_o_atendimento(): void
    {
        $this->ocrComCnh();

        $tela = $this->atendimento()
            ->call('openQuickRegistration')
            ->assertSet('quickEtapa', 'documento')
            ->assertSee('Fotografar documento')
            ->assertDontSee('Analisar documento')
            // A leitura começa ao receber a captura: sem botão "analisar".
            ->set('capturaFrente', $this->imagem(ImagensDocumentoSinteticas::boa(), 'frente.jpg'))
            ->assertSet('quickEtapa', 'conferencia')
            ->assertSet('quickName', 'Pessoa Sintetica')
            ->assertSet('quickDocument', self::CPF)
            ->assertSet('quickDocNumero', '01234567890')
            ->assertSee('Dados sugeridos pela leitura');

        $this->conferido($tela)
            ->call('irParaEtapa', 'foto')
            ->assertHasNoErrors()
            ->assertSet('quickEtapa', 'foto')
            ->set('fotoPessoa', $this->imagem(ImagensPessoaSinteticas::boa(), 'pessoa.jpg'))
            ->assertHasNoErrors()
            ->call('saveQuickRegistration')
            ->assertHasNoErrors()
            ->assertSet('quickRegistrationOpen', false)
            ->assertSet('quickPersonRegistered', true)
            ->assertSet('quickResultado.cadastro.title', 'Cadastro salvo')
            ->assertSet('quickResultado.foto.title', 'Foto salva')
            ->assertSet('quickResultado.facial', null)
            ->assertSet('fotoPessoa', null)
            ->assertDispatched('cadastro-rapido-encerrado')
            // O atendimento em curso não é tocado.
            ->assertSet('contribution', 'yes')
            ->assertSet('paymentMethod', 'pix')
            ->assertSet('ticketNumero', '000777')
            ->assertSet('placaInformada', 'ABC1D23')
            ->assertSet('notes', 'Observação do atendimento.')
            ->assertSee('Cadastro no SDV: Cadastro salvo');

        $pessoa = Pessoa::query()->sole();
        $this->assertSame($pessoa->id, $tela->get('currentPessoaId'));
        $this->assertSame(['cnh', 'cpf'], PessoaDocumento::query()->where('pessoa_id', $pessoa->id)->orderBy('tipo')->pluck('tipo')->all());
        $this->assertSame('01234567890', PessoaDocumento::query()->where('tipo', 'cnh')->value('valor_normalizado'));

        // Foto própria, privada e separada do documento (que não é guardado).
        $foto = PessoaArquivo::query()->with('file')->sole();
        $this->assertSame('foto', $foto->category);
        $this->assertSame('cadastro_rapido', $foto->file->origin);
        Storage::disk(PrivateFileService::DISK)->assertExists($foto->file->object_key);
        $this->assertCount(1, Storage::disk(PrivateFileService::DISK)->allFiles(), 'Só a foto da pessoa é guardada; o documento não.');

        // Salvar não autoriza nem envia nada.
        $this->assertSame(0, AutorizacaoAcesso::query()->count());
        $this->assertSame(0, Operacao::query()->count());
    }

    public function test_rg_cin_pede_o_verso_e_guarda_o_numero_como_rg_nunca_como_cpf(): void
    {
        $this->ocrResponde([
            'nome' => ['valor' => 'Pessoa Sintetica', 'confianca' => 0.99],
            'cpf' => ['valor' => self::CPF, 'confianca' => 0.99],
            'numero_documento' => ['valor' => '12.345.678-9', 'confianca' => 0.99],
        ]);

        $tela = $this->atendimento()
            ->call('openQuickRegistration')
            ->set('capturaTipoDocumento', 'rg_cin')
            ->set('capturaFrente', $this->imagem(ImagensDocumentoSinteticas::boa(), 'frente.jpg'))
            ->assertSet('analiseDocumento', null)
            ->assertSet('quickEtapa', 'documento')
            ->assertSee('Capture o verso para a leitura começar.')
            ->set('capturaVerso', $this->imagem(ImagensDocumentoSinteticas::boa(), 'verso.jpg'))
            ->assertSet('quickEtapa', 'conferencia')
            ->assertSee('Número do RG/CIN');

        $this->conferido($tela)->call('saveQuickRegistration')->assertHasNoErrors();

        $this->assertSame('123456789', PessoaDocumento::query()->where('tipo', 'rg')->value('valor_normalizado'));
        $this->assertSame('52998224725', PessoaDocumento::query()->where('tipo', 'cpf')->value('valor_normalizado'));
    }

    public function test_falha_do_ocr_leva_a_digitacao_manual_sem_perder_o_atendimento(): void
    {
        app(OcrSimulador::class)->responderCom(new ResultadoOcr(false, [], 'Documento não identificado. Faça nova captura ou digite os dados.'));

        $tela = $this->atendimento()
            ->call('openQuickRegistration')
            ->set('capturaFrente', $this->imagem(ImagensDocumentoSinteticas::boa(), 'frente.jpg'))
            ->assertSet('quickEtapa', 'documento')
            ->assertSee('Documento não identificado.')
            ->call('digitarManualmente')
            ->assertSet('quickEtapa', 'conferencia')
            ->set('quickName', 'Digitado Manualmente')
            ->set('quickDocument', self::CPF);

        $this->conferido($tela)->call('saveQuickRegistration')->assertHasNoErrors()->assertSet('notes', 'Observação do atendimento.');
        $this->assertSame('Digitado Manualmente', Pessoa::query()->sole()->nome);
    }

    public function test_nova_leitura_nao_apaga_a_correcao_do_operador(): void
    {
        $this->ocrComCnh();

        $this->atendimento()
            ->call('openQuickRegistration')
            ->set('capturaFrente', $this->imagem(ImagensDocumentoSinteticas::boa(), 'frente.jpg'))
            ->set('quickName', 'Nome Corrigido Pelo Operador')
            ->call('irParaEtapa', 'documento')
            ->set('capturaFrente', $this->imagem(ImagensDocumentoSinteticas::boa(), 'frente.jpg'))
            ->assertSet('quickName', 'Nome Corrigido Pelo Operador')
            ->assertSee('Mantivemos o que você corrigiu');
    }

    public function test_foto_ruim_e_recusada_no_servidor_e_pode_ser_refeita(): void
    {
        $this->atendimento()
            ->call('openQuickRegistration')
            ->set('fotoPessoa', $this->imagem(ImagensPessoaSinteticas::escura(), 'pessoa.jpg'))
            ->assertHasErrors('fotoPessoa')
            ->assertSet('fotoPessoa', null)
            ->set('fotoPessoa', $this->imagem(ImagensPessoaSinteticas::pequena(), 'pessoa.jpg'))
            ->assertHasErrors('fotoPessoa')
            ->set('fotoPessoa', $this->imagem('não é imagem', 'pessoa.jpg'))
            ->assertHasErrors('fotoPessoa')
            ->set('fotoPessoa', $this->imagem(ImagensPessoaSinteticas::boa(), 'pessoa.jpg'))
            ->assertHasNoErrors()
            ->call('refazerFotoPessoa')
            ->assertSet('fotoPessoa', null);
    }

    public function test_cpf_existente_exige_escolha_explicita_e_nao_sobrescreve_identidade_nem_foto(): void
    {
        $existente = Pessoa::query()->create(['nome' => 'Nome Original', 'status' => 'ativo', 'versao' => 1]);
        PessoaDocumento::query()->create(['pessoa_id' => $existente->id, 'tipo' => 'cpf', 'pais' => 'BR', 'valor_normalizado' => '52998224725', 'valor_apresentacao' => self::CPF, 'status' => 'ativo', 'started_at' => now()]);
        app(PrivateFileService::class)->storePessoaFoto($existente, $this->imagem(ImagensPessoaSinteticas::outra(), 'antiga.jpg'));
        $fotoAntiga = $existente->fotoVigente()->arquivo_id;

        $tela = $this->conferido($this->atendimento()->call('openQuickRegistration')->call('digitarManualmente'))
            ->set('quickName', 'Outro Nome Digitado')
            ->set('quickDocument', self::CPF)
            ->call('saveQuickRegistration')
            ->assertHasErrors('quickDocument')
            ->assertSee('Usar este cadastro')
            ->assertSee('Nome Original');
        $this->assertSame(1, Pessoa::query()->count());

        $tela->call('usarCadastroExistente')
            ->assertSet('quickPessoaExistenteId', $existente->id)
            ->call('irParaEtapa', 'foto')
            ->assertSee('Substituir a foto atual do cadastro')
            ->set('fotoPessoa', $this->imagem(ImagensPessoaSinteticas::boa(), 'nova.jpg'))
            ->call('saveQuickRegistration')
            ->assertHasNoErrors()
            ->assertSet('currentPessoaId', $existente->id)
            ->assertSet('quickResultado.cadastro.title', 'Cadastro existente reutilizado')
            ->assertSet('quickResultado.foto.title', 'Foto do cadastro mantida');

        $this->assertSame(1, Pessoa::query()->count());
        $this->assertSame('Nome Original', $existente->refresh()->nome);
        $this->assertSame($fotoAntiga, $existente->fotoVigente()->arquivo_id);
        $this->assertSame(1, Vinculo::query()->where('pessoa_id', $existente->id)->count());
        $this->assertDatabaseHas('auditoria_eventos', ['action' => 'cadastro_rapido_reutilizou_pessoa', 'entity_id' => $existente->id]);
    }

    public function test_troca_de_foto_do_cadastro_existente_so_com_marcacao_e_preserva_o_historico(): void
    {
        $existente = Pessoa::query()->create(['nome' => 'Nome Original', 'status' => 'ativo', 'versao' => 1]);
        PessoaDocumento::query()->create(['pessoa_id' => $existente->id, 'tipo' => 'cpf', 'pais' => 'BR', 'valor_normalizado' => '52998224725', 'valor_apresentacao' => self::CPF, 'status' => 'ativo', 'started_at' => now()]);
        app(PrivateFileService::class)->storePessoaFoto($existente, $this->imagem(ImagensPessoaSinteticas::outra(), 'antiga.jpg'));

        $this->conferido($this->atendimento()->call('openQuickRegistration')->call('digitarManualmente'))
            ->set('quickDocument', self::CPF)
            ->call('usarCadastroExistente')
            ->call('irParaEtapa', 'foto')
            ->set('fotoPessoa', $this->imagem(ImagensPessoaSinteticas::boa(), 'nova.jpg'))
            ->set('quickSubstituirFoto', true)
            ->call('saveQuickRegistration')
            ->assertSet('quickResultado.foto.title', 'Foto salva');

        $this->assertSame(2, PessoaArquivo::query()->where('pessoa_id', $existente->id)->count());
        $this->assertSame(1, PessoaArquivo::query()->where('pessoa_id', $existente->id)->where('is_current', true)->count());
    }

    public function test_cpf_gravado_por_outro_atendimento_entre_a_conferencia_e_o_salvamento_nao_duplica(): void
    {
        $tela = $this->conferido($this->atendimento()->call('openQuickRegistration')->call('digitarManualmente'))
            ->set('quickName', 'Pessoa Concorrente')
            ->set('quickDocument', self::CPF)
            ->call('irParaEtapa', 'foto')
            ->assertSet('quickEtapa', 'foto');

        // Outro posto grava o mesmo CPF enquanto este operador fotografava.
        $outra = Pessoa::query()->create(['nome' => 'Gravada Antes', 'status' => 'ativo', 'versao' => 1]);
        PessoaDocumento::query()->create(['pessoa_id' => $outra->id, 'tipo' => 'cpf', 'pais' => 'BR', 'valor_normalizado' => '52998224725', 'valor_apresentacao' => self::CPF, 'status' => 'ativo', 'started_at' => now()]);

        $tela->call('saveQuickRegistration')->assertHasErrors('quickDocument')->assertSet('quickPersonRegistered', false)
            ->assertSet('quickEtapa', 'conferencia');
        $this->assertSame(1, Pessoa::query()->count());
        $this->assertSame(1, PessoaDocumento::query()->where('tipo', 'cpf')->count());
    }

    public function test_numero_de_documento_de_outra_pessoa_volta_para_a_conferencia_com_o_erro_no_campo(): void
    {
        $outra = Pessoa::query()->create(['nome' => 'Dona do Numero', 'status' => 'ativo', 'versao' => 1]);
        PessoaDocumento::query()->create(['pessoa_id' => $outra->id, 'tipo' => 'cnh', 'pais' => 'BR', 'valor_normalizado' => '12345678900', 'valor_apresentacao' => '12345678900', 'status' => 'ativo', 'started_at' => now()]);

        $tela = $this->conferido($this->atendimento()->call('openQuickRegistration')->call('digitarManualmente'))
            ->set('quickName', 'Pessoa Nova')
            ->set('quickDocument', self::CPF)
            ->set('quickDocNumero', '12345678900')
            ->call('irParaEtapa', 'foto')
            ->assertSet('quickEtapa', 'foto');

        $tela->call('saveQuickRegistration')
            ->assertHasErrors('quickDocNumero')
            ->assertHasNoErrors('quickDocument')
            ->assertSet('quickEtapa', 'conferencia')
            ->assertSet('quickPersonRegistered', false);
        $this->assertSame(1, Pessoa::query()->count());
    }

    /** @return array<string, array{string, string}> */
    public static function camposOpcionaisAusentes(): array
    {
        return [
            'sem telefone' => ['', 'SRA-A-102'],
            'sem imóvel' => ['(12) 99999-1234', ''],
            'sem telefone e sem imóvel' => ['', ''],
        ];
    }

    #[DataProvider('camposOpcionaisAusentes')]
    public function test_salva_cadastro_e_foto_sem_telefone_ou_sem_imovel(string $telefone, string $imovel): void
    {
        // Visitante exige imóvel (docs/021 §14); sem imóvel, o turista entra no lugar dele.
        $tipos = $imovel === '' ? ['turista', 'morador', 'prestador', 'outro'] : ['visitante', 'morador', 'prestador', 'outro'];
        foreach ($tipos as $i => $tipo) {
            $cpf = ['529.982.247-25', '111.444.777-35', '935.411.347-80', '286.255.878-87'][$i];
            $this->atendimento()->call('openQuickRegistration')->call('digitarManualmente')
                ->set('quickName', 'Pessoa Opcional '.$tipo)
                ->set('quickDocument', $cpf)
                ->set('quickPhone', $telefone)
                ->set('quickAccessType', $tipo)
                ->set('quickResponsible', 'Responsável Sintético')
                ->set('quickPropertyCode', $imovel)
                ->call('irParaEtapa', 'foto')
                ->assertHasNoErrors()
                ->assertSet('quickEtapa', 'foto')
                ->set('fotoPessoa', $this->imagem(ImagensPessoaSinteticas::boa(), 'pessoa.jpg'))
                ->call('saveQuickRegistration')
                ->assertHasNoErrors()
                ->assertSet('quickPersonRegistered', true)
                ->assertSet('quickResultado.cadastro.title', 'Cadastro salvo')
                ->assertSet('quickResultado.foto.title', 'Foto salva');
        }

        $this->assertSame(4, Pessoa::query()->count());
        $this->assertSame(4, PessoaArquivo::query()->where('is_current', true)->count());
        // Nada de contato vazio nem vínculo fictício.
        $this->assertSame($telefone === '' ? 0 : 4, PessoaContato::query()->count());
        $this->assertSame(0, PessoaContato::query()->where('valor', '')->count());
        $this->assertSame($imovel === '' ? 0 : 4, Vinculo::query()->count());
        // Salvar nunca cria autorização.
        $this->assertSame(0, AutorizacaoAcesso::query()->count());
    }

    /** @return array<string, array{string, string, string}> */
    public static function valoresInvalidos(): array
    {
        return [
            'telefone com letras' => ['quickPhone', 'abc-telefone', 'SRA-A-102'],
            'telefone curto' => ['quickPhone', '9999-1234', 'SRA-A-102'],
            'telefone longo' => ['quickPhone', '+55 (12) 99999-1234-5678', 'SRA-A-102'],
            'imóvel inexistente' => ['quickPropertyCode', '(12) 99999-1234', 'SRA-Z-999'],
        ];
    }

    #[DataProvider('valoresInvalidos')]
    public function test_telefone_e_imovel_sao_validados_quando_informados(string $campo, string $telefone, string $imovel): void
    {
        $tela = $this->atendimento()->call('openQuickRegistration')->call('digitarManualmente')
            ->set('quickName', 'Pessoa Invalida')
            ->set('quickDocument', self::CPF)
            ->set('quickPhone', $telefone)
            ->set('quickAccessType', 'visitante')
            ->set('quickResponsible', 'Responsável Sintético')
            ->set('quickPropertyCode', $imovel);

        if ($campo === 'quickPhone') {
            $tela->call('irParaEtapa', 'foto')->assertHasErrors('quickPhone')->assertSet('quickEtapa', 'conferencia');
        }

        $tela->call('saveQuickRegistration')
            ->assertHasErrors($campo)
            ->assertSet('quickEtapa', 'conferencia')
            ->assertSet('quickPersonRegistered', false);
        $this->assertSame(0, Pessoa::query()->count());
    }

    public function test_telefone_valido_informado_vira_contato(): void
    {
        $this->atendimento()->call('openQuickRegistration')->call('digitarManualmente')
            ->set('quickName', 'Pessoa Com Telefone')
            ->set('quickDocument', self::CPF)
            ->set('quickPhone', '+55 (12) 99999-1234')
            ->set('quickAccessType', 'turista')
            ->set('quickResponsible', 'Recepção')
            ->call('saveQuickRegistration')
            ->assertHasNoErrors()
            ->assertSet('quickPersonRegistered', true);

        $this->assertSame('+55 (12) 99999-1234', PessoaContato::query()->sole()->valor);
    }

    public function test_clique_duplo_nao_cria_duas_pessoas(): void
    {
        $tela = $this->conferido($this->atendimento()->call('openQuickRegistration')->call('digitarManualmente'))
            ->set('quickName', 'Clique Duplo')
            ->set('quickDocument', self::CPF);

        $tela->call('saveQuickRegistration')->call('saveQuickRegistration')->call('saveQuickRegistrationAndSend');

        $this->assertSame(1, Pessoa::query()->count());
        $this->assertSame(1, Vinculo::query()->count());
        $this->assertSame(0, Operacao::query()->count());
    }

    public function test_cancelar_e_novo_atendimento_limpam_capturas_e_encerram_a_camera(): void
    {
        $this->ocrComCnh();

        $tela = $this->atendimento()
            ->call('openQuickRegistration')
            ->set('capturaFrente', $this->imagem(ImagensDocumentoSinteticas::boa(), 'frente.jpg'))
            ->call('irParaEtapa', 'foto')
            ->set('fotoPessoa', $this->imagem(ImagensPessoaSinteticas::boa(), 'pessoa.jpg'))
            ->call('cancelQuickRegistration')
            ->assertSet('fotoPessoa', null)
            ->assertSet('analiseDocumento', null)
            ->assertSet('quickName', '')
            ->assertSet('quickDocNumero', '')
            ->assertSet('quickEtapa', 'documento')
            ->assertDispatched('cadastro-rapido-encerrado')
            ->assertSet('notes', 'Observação do atendimento.');

        $tela->call('openQuickRegistration')
            ->set('quickName', 'Outra Pessoa')
            ->call('startNewValidation')
            ->assertSet('quickRegistrationOpen', false)
            ->assertSet('quickName', '')
            ->assertSet('quickToken', null)
            ->assertDispatched('cadastro-rapido-encerrado');

        $this->assertSame(0, Pessoa::query()->count());
        $this->assertSame([], Storage::disk(PrivateFileService::DISK)->allFiles());
    }

    public function test_turista_nao_ganha_vinculo_artificial_com_imovel(): void
    {
        $this->atendimento()
            ->call('openQuickRegistration')
            ->call('digitarManualmente')
            ->set('quickName', 'Turista Sintetico')
            ->set('quickDocument', self::CPF)
            ->set('quickPhone', '(12) 99999-1234')
            ->set('quickAccessType', 'turista')
            ->set('quickResponsible', 'Pousada Sintética')
            ->call('saveQuickRegistration')
            ->assertHasNoErrors();

        $this->assertSame(1, Pessoa::query()->count());
        $this->assertSame(0, Vinculo::query()->count());
        $this->assertSame(0, AutorizacaoAcesso::query()->count(), 'Salvar não cria a visita: ela nasce no envio à facial.');
    }

    public function test_permissoes_sao_conferidas_em_cada_acao(): void
    {
        $semCadastro = $this->usuarioCom('validacao.registrar');
        Livewire::actingAs($semCadastro)->test(AccessValidation::class)
            ->assertDontSee('Pessoa não encontrada? Cadastro rápido')
            ->call('openQuickRegistration')
            ->assertForbidden();

        $semFacial = $this->usuarioCom('validacao.registrar', 'pessoas.gerenciar');
        $this->conferido(Livewire::actingAs($semFacial)->test(AccessValidation::class)->call('openQuickRegistration'))
            ->set('quickName', 'Sem Permissão Facial')
            ->set('quickDocument', self::CPF)
            ->call('saveQuickRegistrationAndSend')
            ->assertForbidden();
        $this->assertSame(0, Pessoa::query()->count());
    }

    public function test_permissao_revogada_no_meio_do_atendimento_e_recusada(): void
    {
        $tela = $this->conferido($this->atendimento()->call('openQuickRegistration')->call('digitarManualmente'))
            ->set('quickName', 'Permissão Revogada')
            ->set('quickDocument', self::CPF);

        DB::table('perfil_permissoes')->delete();
        $this->operador->refresh();
        cache()->flush();

        $tela->call('saveQuickRegistration')->assertForbidden();
        $this->assertSame(0, Pessoa::query()->count());
    }
}
