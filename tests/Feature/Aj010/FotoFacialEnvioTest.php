<?php

namespace Tests\Feature\Aj010;

use App\Acesso\Aplicacao\AutorizacoesAcesso;
use App\Acesso\Aplicacao\EnvioFacial;
use App\Integracoes\Adaptadores\Simulador\CenarioSimulador;
use App\Integracoes\Aplicacao\DiagnosticoEquipamento;
use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\EstadoSincronizacao;
use App\Integracoes\Dominio\Enums\OperacaoIntegracao;
use App\Livewire\AccessValidation;
use App\Models\AutorizacaoAcesso;
use App\Models\CredencialSincronizacao;
use App\Models\Equipamento;
use App\Models\FotoFacialSincronizacao;
use App\Models\Imovel;
use App\Models\OperacaoIntegracao as Operacao;
use App\Models\Pessoa;
use App\Models\PessoaArquivo;
use App\Models\User;
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
 * AJ-010 — "Salvar e enviar para a facial" no cadastro rápido, com o
 * resultado do cadastro, da foto e do terminal separados. Somente o
 * simulador: nenhuma foto ou pessoa real vai a um terminal.
 */
class FotoFacialEnvioTest extends TestCase
{
    use MontaCenarioIntegracao, RefreshDatabase;

    private const CPF = '529.982.247-25';

    private Equipamento $terminal;

    private User $operador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararIntegracao();
        Storage::fake(PrivateFileService::DISK);
        $this->terminal = $this->terminalAtivo(['nome' => 'Facial entrada']);
        $this->usarFilaReal();
        $this->operador = $this->usuarioCom('validacao.registrar', 'pessoas.gerenciar', 'facial.enviar');
        Imovel::factory()->create(['codigo' => 'SRA-A-102']);
    }

    protected function tearDown(): void
    {
        $this->encerrarIntegracao();
        parent::tearDown();
    }

    private function foto(string $conteudo = ''): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('pessoa.jpg', $conteudo !== '' ? $conteudo : ImagensPessoaSinteticas::boa());
    }

    private function cadastroRapido(string $tipo = 'morador', bool $comFoto = true): Testable
    {
        $tela = Livewire::actingAs($this->operador)->test(AccessValidation::class)
            ->set('notes', 'Atendimento preservado.')
            ->call('openQuickRegistration')
            ->call('digitarManualmente')
            ->set('quickName', 'Pessoa Sintética')
            ->set('quickDocument', self::CPF)
            ->set('quickPhone', '(12) 99999-1234')
            ->set('quickAccessType', $tipo)
            ->set('quickResponsible', 'Responsável Sintético')
            ->set('quickPropertyCode', 'SRA-A-102')
            ->call('irParaEtapa', 'foto')
            // Com um único terminal possível, ele já vem escolhido; ainda exige confirmação.
            ->assertSet('quickTerminalId', $this->terminal->id);

        return $comFoto ? $tela->set('fotoPessoa', $this->foto()) : $tela;
    }

    private function salvarEEnviar(Testable $tela): Testable
    {
        return $tela->set('quickTerminalConfirmado', true)->call('saveQuickRegistrationAndSend')->assertHasNoErrors()->set('passo', 'concluir');
    }

    public function test_com_a_flag_desligada_a_foto_fica_salva_e_o_envio_aparece_pendente(): void
    {
        $tela = $this->salvarEEnviar($this->cadastroRapido())
            ->assertSet('quickResultado.cadastro.title', 'Cadastro salvo')
            ->assertSet('quickResultado.foto.title', 'Foto salva')
            ->assertSet('quickResultado.facial.title', 'Cadastro confirmado pelo terminal')
            ->assertSet('quickResultado.facial.foto.title', 'Foto salva; envio facial pendente')
            ->assertSee('Foto salva no SDV; envio facial pendente de habilitação/homologação.')
            ->assertSet('notes', 'Atendimento preservado.');

        // Cadastro no terminal (usuário e validade) nunca é apresentado como face.
        $this->assertStringNotContainsString('Pessoa cadastrada na facial', $tela->html());
        $this->assertSame(EstadoSincronizacao::Sincronizado, CredencialSincronizacao::query()->sole()->estado);
        $this->assertSame(EstadoSincronizacao::NaoEnviado, FotoFacialSincronizacao::query()->sole()->estado);
        $this->assertSame(0, Operacao::query()->where('operacao', OperacaoIntegracao::EnviarFotoFacial->value)->count());
        $this->assertSame([], $this->simulador()->fotosGravadas($this->terminal->id));
        $this->assertSame(0, $this->simulador()->totalAberturasFisicas($this->terminal->id));
    }

    public function test_com_a_flag_ligada_o_simulador_recebe_a_foto_depois_do_cadastro(): void
    {
        config(['integracoes.foto_facial_habilitada' => true]);

        $this->salvarEEnviar($this->cadastroRapido())
            ->assertSet('quickResultado.facial.foto.title', 'Foto confirmada pelo terminal')
            ->assertSee('Terminal facial — foto: Foto confirmada pelo terminal');

        $arquivo = PessoaArquivo::query()->with('file')->sole()->file;
        $sincronizacao = FotoFacialSincronizacao::query()->sole();
        $this->assertSame(EstadoSincronizacao::Sincronizado, $sincronizacao->estado);
        $this->assertSame($arquivo->checksum_sha256, $sincronizacao->versao_foto);

        $credencialId = CredencialSincronizacao::query()->sole()->credencial_id;
        $this->assertSame([$credencialId => $arquivo->checksum_sha256], $this->simulador()->fotosGravadas($this->terminal->id));

        // A outbox leva só referências: nenhuma imagem, base64 ou caminho privado.
        $operacao = Operacao::query()->where('operacao', OperacaoIntegracao::EnviarFotoFacial->value)->sole();
        $this->assertSame(['arquivo_id', 'credencial_id', 'pessoa_id', 'versao_arquivo'], collect($operacao->payload)->keys()->sort()->values()->all());
        $this->assertStringNotContainsString($arquivo->object_key, json_encode($operacao->toArray()));
        $this->assertSame(0, $this->simulador()->totalAberturasFisicas($this->terminal->id), 'Enviar foto não aciona relé.');
        $this->assertDatabaseHas('auditoria_eventos', ['action' => 'facial_foto_envio_solicitado']);
    }

    public function test_visitante_recebe_visita_de_no_maximo_24_horas_no_envio(): void
    {
        $this->salvarEEnviar($this->cadastroRapido('visitante', comFoto: false))
            ->assertSet('quickResultado.facial.foto.title', 'Sem foto enviada');

        $autorizacao = AutorizacaoAcesso::query()->sole();
        $this->assertSame(AutorizacaoAcesso::TIPO_TURISTA, $autorizacao->tipo);
        $this->assertLessThanOrEqual(24 * 3600, $autorizacao->inicio->diffInSeconds($autorizacao->fim));
    }

    public function test_sem_imovel_e_sem_autorizacao_o_envio_e_recusado_sem_autorizacao_automatica(): void
    {
        // Prestador sem imóvel: cadastro e foto salvos; sem vínculo, sem autorização, envio recusado.
        $this->salvarEEnviar($this->cadastroRapido('prestador')->set('quickPropertyCode', '')->set('quickPhone', ''))
            ->assertSet('quickResultado.cadastro.title', 'Cadastro salvo')
            ->assertSet('quickResultado.foto.title', 'Foto salva')
            ->assertSet('quickResultado.facial.variant', 'danger');

        $this->assertSame(0, AutorizacaoAcesso::query()->count());
        $this->assertSame(0, Operacao::query()->whereIn('operacao', [OperacaoIntegracao::SincronizarCredencial->value, OperacaoIntegracao::EnviarFotoFacial->value])->count());
        $this->assertSame(1, PessoaArquivo::query()->count());
    }

    public function test_repetir_o_envio_da_mesma_foto_nao_duplica_o_pedido(): void
    {
        config(['integracoes.foto_facial_habilitada' => true]);
        $this->simulador()->definirCenario($this->terminal->id, CenarioSimulador::Timeout, OperacaoIntegracao::EnviarFotoFacial);
        $this->salvarEEnviar($this->cadastroRapido());

        $pessoa = Pessoa::query()->sole();
        $resultado = app(EnvioFacial::class)->enviar($pessoa, $this->terminal->id, $this->operador);

        $this->assertSame('ja_solicitado', $resultado->foto['situacao']);
        $this->assertSame(1, Operacao::query()->where('operacao', OperacaoIntegracao::EnviarFotoFacial->value)->count());
    }

    public function test_timeout_nao_vira_sucesso_e_terminal_fora_do_ar_mantem_cadastro_e_foto(): void
    {
        config(['integracoes.foto_facial_habilitada' => true]);
        $this->simulador()->definirCenario($this->terminal->id, CenarioSimulador::Timeout, OperacaoIntegracao::EnviarFotoFacial);

        $this->salvarEEnviar($this->cadastroRapido())
            ->assertSet('quickResultado.facial.foto.title', 'Confirmação da foto pendente')
            ->assertSet('quickResultado.cadastro.title', 'Cadastro salvo')
            ->assertSet('quickResultado.foto.title', 'Foto salva');
        $this->assertSame(EstadoSincronizacao::AtualizacaoPendente, FotoFacialSincronizacao::query()->sole()->estado);

        // Terminal inalcançável: nada se perde no SDV.
        config()->set('integracoes.outbox.max_tentativas', 1);
        FotoFacialSincronizacao::query()->delete();
        $this->simulador()->definirCenario($this->terminal->id, CenarioSimulador::Indisponivel, OperacaoIntegracao::EnviarFotoFacial);
        $resultado = app(EnvioFacial::class)->enviar(Pessoa::query()->sole(), $this->terminal->id, $this->operador);
        $this->assertSame('intervencao_necessaria', $resultado->foto['estado']);
        $this->assertSame(1, Pessoa::query()->count());
        $this->assertSame(1, PessoaArquivo::query()->count());
    }

    public function test_foto_nova_invalida_a_sincronizada_e_gera_novo_envio_controlado(): void
    {
        config(['integracoes.foto_facial_habilitada' => true]);
        $this->salvarEEnviar($this->cadastroRapido());
        $pessoa = Pessoa::query()->sole();
        $anterior = FotoFacialSincronizacao::query()->sole()->versao_foto;

        app(PrivateFileService::class)->storePessoaFoto($pessoa, $this->foto(ImagensPessoaSinteticas::outra()));

        $registro = FotoFacialSincronizacao::query()->sole();
        $this->assertSame(EstadoSincronizacao::NaoEnviado, $registro->estado);
        $this->assertNotSame($anterior, $registro->versao_foto);

        $resultado = app(EnvioFacial::class)->enviar($pessoa, $this->terminal->id, $this->operador);
        $this->assertSame('sincronizado', $resultado->foto['estado']);
        $this->assertSame(2, Operacao::query()->where('operacao', OperacaoIntegracao::EnviarFotoFacial->value)->count());
        $this->assertSame([$registro->credencial_id => $registro->versao_foto], $this->simulador()->fotosGravadas($this->terminal->id));
    }

    public function test_revogacao_remove_o_usuario_e_a_face_do_terminal(): void
    {
        config(['integracoes.foto_facial_habilitada' => true]);
        $this->salvarEEnviar($this->cadastroRapido('visitante'));

        app(AutorizacoesAcesso::class)->encerrar(AutorizacaoAcesso::query()->sole(), 'Saída antecipada do visitante', $this->usuarioCom('autorizacoes.gerenciar'));

        $this->assertSame(EstadoSincronizacao::Removido, CredencialSincronizacao::query()->sole()->estado);
        $this->assertSame(EstadoSincronizacao::Removido, FotoFacialSincronizacao::query()->sole()->estado);
        $this->assertSame([], $this->simulador()->fotosGravadas($this->terminal->id));
        $this->assertSame([], $this->simulador()->credenciaisGravadas($this->terminal->id));
    }

    public function test_terminal_sem_a_capacidade_homologada_nao_recebe_foto(): void
    {
        config(['integracoes.foto_facial_habilitada' => true]);
        $this->simulador()->definirCapacidades($this->terminal->id, array_values(array_filter(Capacidade::cases(), fn ($c) => $c !== Capacidade::CredencialFacial)));
        $this->processar(app(DiagnosticoEquipamento::class)->consultarCapacidades($this->terminal->refresh()));

        $this->salvarEEnviar($this->cadastroRapido())
            ->assertSee('Foto salva no SDV; o envio de foto ainda não está homologado para este terminal.');
        $this->assertSame(0, Operacao::query()->where('operacao', OperacaoIntegracao::EnviarFotoFacial->value)->count());
    }

    public function test_flag_desligada_depois_do_pedido_interrompe_o_envio_no_worker(): void
    {
        config(['integracoes.foto_facial_habilitada' => true]);
        $this->simulador()->definirCenario($this->terminal->id, CenarioSimulador::Indisponivel, OperacaoIntegracao::EnviarFotoFacial);
        $this->salvarEEnviar($this->cadastroRapido());
        $operacao = Operacao::query()->where('operacao', OperacaoIntegracao::EnviarFotoFacial->value)->sole();

        config(['integracoes.foto_facial_habilitada' => false]);
        $operacao->forceFill(['disponivel_em' => now()->subMinute()])->save();
        $this->processar($operacao);

        $this->assertSame('recusado', $operacao->refresh()->resultado->value);
        $this->assertDatabaseHas('integracao_ocorrencias', ['operacao_integracao_id' => $operacao->id, 'codigo' => 'foto_facial_desabilitada']);
        $this->assertSame(EstadoSincronizacao::Falha, FotoFacialSincronizacao::query()->sole()->estado);
        $this->assertSame([], $this->simulador()->fotosGravadas($this->terminal->id));
    }

    public function test_foto_de_outra_pessoa_e_recusada_pelo_worker(): void
    {
        config(['integracoes.foto_facial_habilitada' => true]);
        $this->simulador()->definirCenario($this->terminal->id, CenarioSimulador::Indisponivel, OperacaoIntegracao::EnviarFotoFacial);
        $this->salvarEEnviar($this->cadastroRapido());
        $operacao = Operacao::query()->where('operacao', OperacaoIntegracao::EnviarFotoFacial->value)->sole();

        $outra = Pessoa::query()->create(['nome' => 'Outra Pessoa', 'status' => 'ativo', 'versao' => 1]);
        $arquivoOutra = app(PrivateFileService::class)->storePessoaFoto($outra, $this->foto(ImagensPessoaSinteticas::outra()));
        // Pedido adulterado: arquivo de outra pessoa com a referência desta.
        $operacao->forceFill(['payload' => [...$operacao->payload, 'arquivo_id' => $arquivoOutra->id, 'versao_arquivo' => $arquivoOutra->checksum_sha256], 'disponivel_em' => now()->subMinute()])->save();
        $this->simulador()->definirCenario($this->terminal->id, CenarioSimulador::Sucesso, OperacaoIntegracao::EnviarFotoFacial);
        $this->processar($operacao);

        $this->assertSame('recusado', $operacao->refresh()->resultado->value);
        $this->assertSame([], $this->simulador()->fotosGravadas($this->terminal->id));
    }
}
