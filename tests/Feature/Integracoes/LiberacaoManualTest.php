<?php

namespace Tests\Feature\Integracoes;

use App\Integracoes\Adaptadores\Simulador\CenarioSimulador;
use App\Integracoes\Aplicacao\CadastroPostos;
use App\Integracoes\Aplicacao\ComandosAbertura;
use App\Integracoes\Aplicacao\DiagnosticoEquipamento;
use App\Integracoes\Aplicacao\FabricaContextoEquipamento;
use App\Integracoes\Aplicacao\LiberacaoManual;
use App\Integracoes\Aplicacao\ProcessadorOperacoes;
use App\Integracoes\Aplicacao\TurnosOperacionais;
use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\Direcao;
use App\Integracoes\Dominio\Enums\EstadoSaude;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Integracoes\Dominio\Enums\StatusPontoAcesso;
use App\Integracoes\Dominio\Excecoes\PayloadDivergente;
use App\Livewire\AccessHistory;
use App\Livewire\LiberacaoManualPortaria;
use App\Livewire\SelecaoPosto;
use App\Models\AuditoriaEvento;
use App\Models\Equipamento;
use App\Models\HistoricoAcesso;
use App\Models\Implantacao;
use App\Models\OperadorTurno;
use App\Models\Pessoa;
use App\Models\Posto;
use App\Models\User;
use App\Models\UsuarioImplantacao;
use App\Models\Vinculo;
use App\Support\ImplantacaoContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\TestCase;

/**
 * Liberação manual operacional (ADR-017 1.1.0, SDV-OPE-017/018): posto do
 * turno, relés preferenciais, alcance ampliado e exceção por checkbox.
 */
class LiberacaoManualTest extends TestCase
{
    use MontaCenarioIntegracao, RefreshDatabase;

    private const SENHA = 'Senha-Tecnica-Operacao-999';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararIntegracao();
        config(['integracoes.liberacao_operacional_habilitada' => true]);
    }

    protected function tearDown(): void
    {
        $this->encerrarIntegracao();
        parent::tearDown();
    }

    // ---- Cenário ---------------------------------------------------------------

    /** Terminal ativo em ponto real, online, homologado e com relé homologado. */
    private function terminalOperacional(string $nome, string $tipoPonto = 'cancela', string $direcaoEquipamento = 'entrada'): Equipamento
    {
        $ponto = $this->novoPonto(['nome' => 'Ponto '.$nome, 'tipo' => $tipoPonto, 'direcao_suportada' => 'bidirecional']);
        $this->cadastro()->alterarStatusPonto($ponto, StatusPontoAcesso::Ativo, 'operação');
        $equipamento = $this->novoEquipamento(['nome' => $nome, 'direcao' => $direcaoEquipamento]);
        $this->cadastro()->vincularAoPonto($equipamento, $ponto);
        $this->cadastro()->definirSenhaTecnica($equipamento, 'admin', self::SENHA);
        $this->cadastro()->confiarCertificado($equipamento, str_repeat('B', 43).'=', 'IMPRESSAO-SINTETICA');
        $this->processar(app(DiagnosticoEquipamento::class)->consultarCapacidades($equipamento->refresh()));
        $this->processar(app(DiagnosticoEquipamento::class)->testarConexao($equipamento->refresh()));
        $equipamento->refresh()->forceFill(['rele_homologado_em' => now()])->save();

        return $this->cadastro()->alterarStatus($equipamento->refresh(), StatusEquipamento::Ativo, 'operação');
    }

    private function operador(string ...$extras): User
    {
        $usuario = $this->usuarioCom('validacao.registrar', ComandosAbertura::PERMISSAO_LIBERACAO_MANUAL, ...$extras);
        UsuarioImplantacao::query()->create(['user_id' => $usuario->id, 'status' => 'ativa']);

        return $usuario;
    }

    /** Cria o posto e vincula os pontos dos terminais (qualquer operador assume qualquer posto). */
    private function posto(string $tipo, array $terminais): Posto
    {
        $cadastro = app(CadastroPostos::class);
        $posto = $cadastro->criarPosto('P-'.Str::upper(Str::random(6)), 'Guarita '.$tipo, null, $tipo);
        foreach ($terminais as $terminal) {
            $cadastro->vincularPonto($posto, $terminal->pontoVigente());
        }

        return $posto;
    }

    private function assumir(User $operador, Posto $posto): void
    {
        app(TurnosOperacionais::class)->assumir($operador, $posto->id);
    }

    private function pessoaAutorizada(): Pessoa
    {
        return Vinculo::factory()->create()->pessoa;
    }

    private function liberar(User $operador, Equipamento $equipamento, array $extra = [], ?string $chave = null): HistoricoAcesso
    {
        $evento = app(LiberacaoManual::class)->solicitar($operador, array_merge([
            'equipamento_id' => $equipamento->id,
            'direcao' => 'entrada',
            'pessoa_id' => $this->pessoaAutorizada()->id,
            'motivo' => 'Morador sem credencial no momento',
        ], $extra), $chave ?? 'liberacao-manual:'.Str::uuid7());

        $this->processar($evento->operacaoIntegracao);

        return $evento->refresh();
    }

    private function nomes(array $itens): array
    {
        return array_map(fn ($i) => $i['equipamento']->nome, $itens);
    }

    // ---- Posto do turno --------------------------------------------------------

    public function test_seleciona_os_tres_postos_registra_troca_e_encerramento(): void
    {
        $operador = $this->operador();
        $principal = $this->posto(Posto::TIPO_PRINCIPAL, []);
        $veiculos = $this->posto(Posto::TIPO_VEICULOS, []);
        $pedestres = $this->posto(Posto::TIPO_PEDESTRES, []);
        $turnos = app(TurnosOperacionais::class);
        $this->actingAs($operador);

        foreach ([$principal, $veiculos, $pedestres] as $posto) {
            $turnos->assumir($operador, $posto->id);
            $this->assertSame($posto->id, $turnos->turnoAtivo($operador)->posto_id);
        }

        $this->assertSame(3, OperadorTurno::query()->where('user_id', $operador->id)->count());
        $this->assertSame(2, OperadorTurno::query()->where('motivo_encerramento', TurnosOperacionais::MOTIVO_TROCA)->count());
        $this->assertSame(2, AuditoriaEvento::query()->where('action', 'posto_operacional_trocado')->count());

        $this->post('/sair')->assertRedirect(route('login'));
        $this->assertNull(OperadorTurno::query()->where('user_id', $operador->id)->whereNull('encerrado_em')->first());
        $this->assertSame(TurnosOperacionais::MOTIVO_SAIDA, OperadorTurno::query()->where('posto_id', $pedestres->id)->value('motivo_encerramento'));
    }

    public function test_qualquer_operador_assume_posto_livre_e_posto_ocupado_e_recusado(): void
    {
        $tatiane = $this->operador();
        $outro = $this->operador();
        $principal = $this->posto(Posto::TIPO_PRINCIPAL, []);
        $pedestres = $this->posto(Posto::TIPO_PEDESTRES, []);
        $turnos = app(TurnosOperacionais::class);

        $turnos->assumir($tatiane, $principal->id);
        $this->assertRegraViolada('posto_ocupado', fn () => $turnos->assumir($outro, $principal->id));

        $this->actingAs($outro)->get('/portaria')->assertRedirect(route('posto.selecionar', ['voltar' => '/portaria']));

        Livewire::actingAs($outro)->test(SelecaoPosto::class)
            ->assertSee($principal->nome)
            ->assertSee('Ocupado por '.$tatiane->name)
            ->assertSee($pedestres->nome)
            ->call('assumir', $principal->id)
            ->assertSee('precisa fechar o caixa e encerrar o turno')
            ->call('assumir', $pedestres->id)
            ->assertRedirect('/portaria');

        $this->actingAs($outro)->get('/portaria')->assertOk()->assertSee('Posto: '.$pedestres->nome);

        $turnos->encerrar($tatiane);
        $turnos->assumir($outro, $principal->id);
        $this->assertSame($principal->id, $turnos->turnoAtivo($outro)->posto_id, 'Posto liberado pode ser assumido por outro operador.');
    }

    // ---- Relés preferenciais e alcance -------------------------------------------

    public function test_postos_mostram_seus_reles_primeiro_e_principal_ve_todos(): void
    {
        $cancela = $this->terminalOperacional('Facial Entrada Veículos', 'cancela');
        $catraca = $this->terminalOperacional('Facial Entrada Pedestres', 'catraca');
        $operador = $this->operador(ComandosAbertura::PERMISSAO_LIBERAR_OUTROS_RELES);
        $comum = $this->operador();
        $principal = $this->posto(Posto::TIPO_PRINCIPAL, []);
        $veiculos = $this->posto(Posto::TIPO_VEICULOS, [$cancela]);
        $pedestres = $this->posto(Posto::TIPO_PEDESTRES, [$catraca]);
        $servico = app(LiberacaoManual::class);

        $todos = $servico->equipamentosDisponiveis($comum, $principal, Direcao::Entrada);
        $this->assertEqualsCanonicalizing(['Facial Entrada Veículos', 'Facial Entrada Pedestres'], $this->nomes($todos['preferenciais']));

        $deVeiculos = $servico->equipamentosDisponiveis($operador, $veiculos, Direcao::Entrada);
        $this->assertSame(['Facial Entrada Veículos'], $this->nomes($deVeiculos['preferenciais']));
        $this->assertSame(['Facial Entrada Pedestres'], $this->nomes($deVeiculos['outros']));

        $dePedestres = $servico->equipamentosDisponiveis($operador, $pedestres, Direcao::Entrada);
        $this->assertSame(['Facial Entrada Pedestres'], $this->nomes($dePedestres['preferenciais']));

        $this->assertSame([], $servico->equipamentosDisponiveis($comum, $veiculos, Direcao::Entrada)['outros'], 'Operador comum não vê outros relés.');
    }

    public function test_operador_comum_nao_aciona_rele_externo_e_ampliado_aciona_com_confirmacao(): void
    {
        $cancela = $this->terminalOperacional('Facial Entrada Veículos', 'cancela');
        $catraca = $this->terminalOperacional('Facial Entrada Pedestres', 'catraca');
        $comum = $this->operador();
        $ampliado = $this->operador(ComandosAbertura::PERMISSAO_LIBERAR_OUTROS_RELES);
        $veiculos = $this->posto(Posto::TIPO_VEICULOS, [$cancela]);
        $this->assumir($comum, $veiculos);

        $this->assertRegraViolada('fora_do_posto_sem_permissao', fn () => $this->liberar($comum, $catraca, ['confirmou_fora_do_posto' => true]));
        $doPosto = $this->liberar($comum, $cancela);
        $this->assertFalse($doPosto->fora_do_posto);

        // Um posto, uma pessoa: o comum encerra o turno antes do ampliado assumir.
        app(TurnosOperacionais::class)->encerrar($comum);
        $this->assumir($ampliado, $veiculos);
        $this->assertRegraViolada('confirmacao_fora_do_posto', fn () => $this->liberar($ampliado, $catraca));
        $this->assertSame(0, $this->simulador()->totalAberturasFisicas($catraca->id));

        $evento = $this->liberar($ampliado, $catraca, ['confirmou_fora_do_posto' => true]);

        $this->assertTrue($evento->fora_do_posto);
        $this->assertSame($veiculos->id, $evento->posto_id);
        $this->assertNotNull($evento->operador_turno_id);
        $this->assertSame(1, $this->simulador()->totalAberturasFisicas($catraca->id));
        $this->assertTrue(AuditoriaEvento::query()->where('action', 'liberacao_manual_solicitada')->with('context')->get()->contains(fn ($a) => $a->context->metadata['fora_do_posto'] === true));
    }

    public function test_tela_destaca_confirmacao_fora_do_posto(): void
    {
        $cancela = $this->terminalOperacional('Facial Entrada Veículos', 'cancela');
        $catraca = $this->terminalOperacional('Facial Entrada Pedestres', 'catraca');
        $ampliado = $this->operador(ComandosAbertura::PERMISSAO_LIBERAR_OUTROS_RELES);
        $veiculos = $this->posto(Posto::TIPO_VEICULOS, [$cancela]);
        $this->assumir($ampliado, $veiculos);
        $pessoa = $this->pessoaAutorizada();

        Livewire::actingAs($ampliado)->test(LiberacaoManualPortaria::class)
            ->assertSee('Outros pontos de acesso (1)')
            ->call('selecionarPessoa', $pessoa->id)
            ->set('mostrarOutros', true)
            ->set('equipamentoId', $catraca->id)
            ->set('motivo', 'Visitante na portaria de pedestres')
            ->call('revisar')
            ->assertSee("Você está operando no posto {$veiculos->nome} e selecionou o ponto Ponto Facial Entrada Pedestres. Confirma o acionamento deste relé?")
            ->call('confirmar')
            ->assertSee('Fora do posto');

        $this->assertSame(1, $this->simulador()->totalAberturasFisicas($catraca->id));
    }

    // ---- Isolamento e dados do navegador -----------------------------------------

    public function test_nenhuma_operacao_cruza_implantacoes(): void
    {
        $equipamento = $this->terminalOperacional('Facial Entrada Principal');
        $this->posto(Posto::TIPO_PRINCIPAL, [], $this->operador());

        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        $intruso = $this->operador(ComandosAbertura::PERMISSAO_LIBERAR_OUTROS_RELES);
        $this->assumir($intruso, $this->posto(Posto::TIPO_PRINCIPAL, []));

        try {
            $this->liberar($intruso, $equipamento, ['confirmou_fora_do_posto' => true]);
            $this->fail('Equipamento de outra implantação foi acionado.');
        } catch (ModelNotFoundException) {
            $this->assertSame(0, $this->simulador()->totalAberturasFisicas($equipamento->id));
        } finally {
            ImplantacaoContext::setCurrentForTesting($this->implantacao);
        }
    }

    public function test_backend_ignora_ip_porta_e_credencial_enviados_pelo_frontend(): void
    {
        $equipamento = $this->terminalOperacional('Facial Entrada Principal');
        $operador = $this->operador();
        $this->assumir($operador, $this->posto(Posto::TIPO_PRINCIPAL, []));

        $evento = $this->liberar($operador, $equipamento, [
            'endereco_rede' => '10.66.66.66', 'porta_rede' => 9999, 'porta_rele' => 2,
            'senha' => 'senha-do-navegador', 'usuario_tecnico' => 'invasor', 'tls_pin_sha256' => 'x',
        ]);

        $contexto = app(FabricaContextoEquipamento::class)->para($equipamento->refresh(), (string) Str::uuid7());
        $this->assertSame($equipamento->endereco_rede, $contexto->enderecoRede);
        $this->assertSame(1, $contexto->portaRele);
        $this->assertSame(self::SENHA, $contexto->segredo()->revelar());
        $this->assertSame(1, $evento->operacaoIntegracao->payload['porta_rele']);
        $this->assertStringNotContainsString('10.66.66.66', json_encode(DB::table('operacoes_integracao')->get()));
        $this->assertStringNotContainsString('senha-do-navegador', json_encode(DB::table('auditoria_contextos')->get()));
    }

    // ---- Requisitos técnicos e de permissão --------------------------------------

    public function test_sem_permissao_sem_posto_ou_com_requisito_tecnico_invalido_nao_aciona(): void
    {
        $equipamento = $this->terminalOperacional('Facial Entrada Principal');
        $semPermissao = $this->usuarioCom('validacao.registrar', ComandosAbertura::PERMISSAO_LIBERAR);
        UsuarioImplantacao::query()->create(['user_id' => $semPermissao->id, 'status' => 'ativa']);
        $operador = $this->operador();
        $posto = $this->posto(Posto::TIPO_PRINCIPAL, []);

        $this->assertRegraViolada('sem_posto_ativo', fn () => $this->liberar($operador, $equipamento));
        $this->assumir($semPermissao, $posto);
        $this->assertRegraViolada('sem_permissao', fn () => $this->liberar($semPermissao, $equipamento));
        app(TurnosOperacionais::class)->encerrar($semPermissao);
        $this->assumir($operador, $posto);

        $casos = [
            'equipamento_offline' => fn (Equipamento $e) => $e->forceFill(['estado_saude' => EstadoSaude::Indisponivel])->save(),
            'equipamento_nao_operacional' => fn (Equipamento $e) => $this->cadastro()->alterarStatus($e, StatusEquipamento::Manutencao, 'manutenção'),
            'certificado_nao_confiavel' => fn (Equipamento $e) => $e->forceFill(['tls_pin_sha256' => null])->save(),
            'firmware_nao_homologado' => fn (Equipamento $e) => $e->capacidades()->where('capacidade', Capacidade::AberturaRemota->value)->update(['estado_homologacao' => 'em_homologacao']),
            'rele_nao_homologado' => fn (Equipamento $e) => $e->forceFill(['rele_homologado_em' => null])->save(),
        ];
        foreach ($casos as $codigo => $preparar) {
            $alvo = $this->terminalOperacional('Facial '.$codigo);
            $preparar($alvo);
            $this->assertRegraViolada($codigo, fn () => $this->liberar($operador, $alvo->refresh()));
            $this->assertSame(0, $this->simulador()->totalAberturasFisicas($alvo->id), $codigo);
        }

        $this->assertRegraViolada('direcao_incompativel', fn () => $this->liberar($operador, $equipamento, ['direcao' => 'saida']));
        config(['integracoes.liberacao_operacional_habilitada' => false]);
        $this->assertRegraViolada('liberacao_operacional_desabilitada', fn () => $this->liberar($operador, $equipamento));
        $this->assertSame(0, $this->simulador()->totalAberturasFisicas($equipamento->id));
    }

    // ---- Liberação excepcional por checkbox --------------------------------------

    public function test_checkbox_excepcional_aplica_motivo_padronizado_sem_texto_livre(): void
    {
        $equipamento = $this->terminalOperacional('Facial Entrada Principal');
        $operador = $this->operador(ComandosAbertura::PERMISSAO_LIBERACAO_EXCEPCIONAL);
        $this->assumir($operador, $this->posto(Posto::TIPO_PRINCIPAL, []));
        $semVinculo = Pessoa::factory()->create();

        $this->assertRegraViolada('sem_vinculo_vigente', fn () => $this->liberar($operador, $equipamento, ['pessoa_id' => $semVinculo->id]));

        $evento = $this->liberar($operador, $equipamento, ['pessoa_id' => $semVinculo->id, 'excepcional' => true, 'motivo' => null]);

        $this->assertTrue($evento->excepcional);
        $this->assertSame(ComandosAbertura::MOTIVO_EXCEPCIONAL, $evento->motivo);
        $this->assertSame($semVinculo->id, $evento->pessoa_id, 'Pessoa disponível continua associada.');
        $this->assertNull($evento->justificativa_excepcional);

        $auditoria = AuditoriaEvento::query()->where('action', 'liberacao_manual_excepcional_solicitada')->with('context')->sole();
        $this->assertSame('restrita', $auditoria->classification);
        $this->assertSame(ComandosAbertura::MOTIVO_EXCEPCIONAL, $auditoria->context->metadata['motivo_padronizado']);

        $semPessoa = $this->liberar($operador, $equipamento, ['pessoa_id' => null, 'excepcional' => true, 'observacao' => 'Entregador sem cadastro']);
        $this->assertNull($semPessoa->pessoa_id);
        $this->assertSame('Entregador sem cadastro', $semPessoa->justificativa_excepcional);
    }

    public function test_relatorio_de_entradas_destaca_excepcional_e_fora_do_posto(): void
    {
        $cancela = $this->terminalOperacional('Facial Entrada Veículos', 'cancela');
        $catraca = $this->terminalOperacional('Facial Entrada Pedestres', 'catraca');
        $operador = $this->operador(ComandosAbertura::PERMISSAO_LIBERACAO_EXCEPCIONAL, ComandosAbertura::PERMISSAO_LIBERAR_OUTROS_RELES);
        $this->assumir($operador, $this->posto(Posto::TIPO_VEICULOS, [$cancela]));
        $this->liberar($operador, $catraca, ['pessoa_id' => null, 'excepcional' => true, 'confirmou_fora_do_posto' => true]);

        Livewire::actingAs($operador)->test(AccessHistory::class)
            ->assertSee('Excepcional')
            ->assertSee('Fora do posto');
    }

    public function test_observacao_pode_ser_exigida_por_configuracao(): void
    {
        config(['integracoes.liberacao_excepcional_observacao_obrigatoria' => true]);
        $equipamento = $this->terminalOperacional('Facial Entrada Principal');
        $operador = $this->operador(ComandosAbertura::PERMISSAO_LIBERACAO_EXCEPCIONAL);
        $this->assumir($operador, $this->posto(Posto::TIPO_PRINCIPAL, []));

        $this->assertRegraViolada('sem_observacao', fn () => $this->liberar($operador, $equipamento, ['pessoa_id' => null, 'excepcional' => true]));
    }

    public function test_usuario_sem_permissao_nao_usa_o_checkbox(): void
    {
        $equipamento = $this->terminalOperacional('Facial Entrada Principal');
        $operador = $this->operador();
        $this->assumir($operador, $this->posto(Posto::TIPO_PRINCIPAL, []));

        $this->assertRegraViolada('sem_permissao_excepcional', fn () => $this->liberar($operador, $equipamento, ['pessoa_id' => null, 'excepcional' => true]));

        Livewire::actingAs($operador)->test(LiberacaoManualPortaria::class)
            ->assertDontSee('Liberação excepcional — prosseguir sem autorização regular confirmada')
            ->set('excepcional', true)
            ->assertSet('excepcional', false)
            ->assertHasErrors('excepcional');
    }

    public function test_tela_excepcional_exige_confirmacao_destacada_e_destaca_o_evento(): void
    {
        $equipamento = $this->terminalOperacional('Facial Entrada Principal');
        $operador = $this->operador(ComandosAbertura::PERMISSAO_LIBERACAO_EXCEPCIONAL);
        $this->assumir($operador, $this->posto(Posto::TIPO_PRINCIPAL, []));

        Livewire::actingAs($operador)->test(LiberacaoManualPortaria::class)
            ->set('excepcional', true)
            ->call('revisar')
            ->assertHasNoErrors()
            ->assertSee('Você está liberando sem autorização regular confirmada')
            ->call('confirmar')
            ->assertSee('Excepcional');

        $this->assertTrue(HistoricoAcesso::query()->where('origem', 'liberacao_manual')->value('excepcional'));
    }

    // ---- Idempotência, resultado e sigilo ----------------------------------------

    public function test_clique_duplo_e_repeticao_sao_idempotentes(): void
    {
        $equipamento = $this->terminalOperacional('Facial Entrada Principal');
        $operador = $this->operador();
        $this->assumir($operador, $this->posto(Posto::TIPO_PRINCIPAL, []));
        $pessoa = $this->pessoaAutorizada();
        $chave = 'liberacao-manual:'.Str::uuid7();

        $primeira = $this->liberar($operador, $equipamento, ['pessoa_id' => $pessoa->id], $chave);
        $segunda = $this->liberar($operador, $equipamento, ['pessoa_id' => $pessoa->id], $chave);

        $this->assertSame($primeira->id, $segunda->id);
        $this->assertSame(1, $this->simulador()->totalAberturasFisicas($equipamento->id));

        $this->expectException(PayloadDivergente::class);
        $this->liberar($operador, $equipamento, ['pessoa_id' => $pessoa->id, 'direcao' => 'saida'], $chave);
    }

    public function test_timeout_gera_confirmacao_desconhecida_sem_reenvio(): void
    {
        $equipamento = $this->terminalOperacional('Facial Entrada Principal');
        $operador = $this->operador();
        $this->assumir($operador, $this->posto(Posto::TIPO_PRINCIPAL, []));
        $this->simulador()->definirCenario($equipamento->id, CenarioSimulador::Timeout);

        $evento = $this->liberar($operador, $equipamento);

        $this->assertSame('desconhecida', $evento->resultado_tecnico);
        $this->assertNull(app(ProcessadorOperacoes::class)->processar($evento->operacao_integracao_id));
    }

    public function test_tela_normal_confirma_e_nao_apresenta_aceite_como_passagem(): void
    {
        $equipamento = $this->terminalOperacional('Facial Entrada Principal');
        $operador = $this->operador();
        $this->assumir($operador, $this->posto(Posto::TIPO_PRINCIPAL, []));
        $pessoa = $this->pessoaAutorizada();
        $this->simulador()->definirCenario($equipamento->id, CenarioSimulador::Aceito);

        Livewire::actingAs($operador)->test(LiberacaoManualPortaria::class)
            ->call('selecionarPessoa', $pessoa->id)
            ->assertSee('Facial Entrada Principal')
            ->set('motivo', 'Morador sem credencial no momento')
            ->call('revisar')
            ->assertSee('Você está prestes a acionar Facial Entrada Principal, no ponto Ponto Facial Entrada Principal. Confirma a liberação?')
            ->call('confirmar')
            ->assertSee('Aceita pelo terminal')
            ->assertSee('não confirma a passagem física')
            ->assertDontSee(self::SENHA)
            ->assertDontSee($equipamento->endereco_rede);
    }

    public function test_tela_explica_o_que_falta_e_respeita_flag_e_permissao(): void
    {
        $equipamento = $this->terminalOperacional('Facial Entrada Principal');
        $operador = $this->operador();
        $this->assumir($operador, $this->posto(Posto::TIPO_PRINCIPAL, []));
        $equipamento->forceFill(['rele_homologado_em' => null])->save();

        Livewire::actingAs($operador)->test(LiberacaoManualPortaria::class)
            ->assertSee('O que falta')
            ->assertSee('O relé deste terminal ainda não foi homologado.');

        config(['integracoes.liberacao_operacional_habilitada' => false]);
        Livewire::actingAs($operador)->test(LiberacaoManualPortaria::class)->assertSee('Liberação manual desligada');
        $this->actingAs($this->usuarioCom('validacao.registrar'))->get('/portaria')->assertOk()->assertDontSee('Liberação manual');
    }

    public function test_tela_de_postos_exige_permissao_e_posto_ocupado_nao_e_desativado(): void
    {
        $this->actingAs($this->usuarioCom('validacao.registrar'))->get('/postos')->assertRedirect(route('home'));
        $this->actingAs($this->usuarioCom('integracoes.gerenciar'))->get('/postos')->assertOk()->assertSee('Como funcionam os postos');
        $this->actingAs($this->usuarioCom(TurnosOperacionais::PERMISSAO_LIBERAR))->get('/postos')->assertOk();

        $operador = $this->operador();
        $posto = $this->posto(Posto::TIPO_VEICULOS, []);
        $this->assumir($operador, $posto);
        $cadastro = app(CadastroPostos::class);

        $this->assertRegraViolada('posto_ocupado', fn () => $cadastro->alterarStatus($posto, false));
        app(TurnosOperacionais::class)->encerrar($operador);
        $cadastro->alterarStatus($posto, false);
        $this->assertSame('inativo', $posto->refresh()->status);

        $cadastro->editarPosto($posto, 'Guarita Veículos', Posto::TIPO_PEDESTRES);
        $this->assertSame(Posto::TIPO_PEDESTRES, $posto->refresh()->tipo);
        $this->assertTrue(AuditoriaEvento::query()->where('action', 'posto_desativado')->exists());
        $this->assertTrue(AuditoriaEvento::query()->where('action', 'posto_editado')->exists());
    }

    public function test_nenhum_segredo_em_log_auditoria_ou_evento(): void
    {
        Log::spy();
        $equipamento = $this->terminalOperacional('Facial Entrada Principal');
        $operador = $this->operador();
        $this->assumir($operador, $this->posto(Posto::TIPO_PRINCIPAL, []));

        $this->liberar($operador, $equipamento);

        $tudo = json_encode([
            DB::table('auditoria_eventos')->get(), DB::table('auditoria_alteracoes')->get(), DB::table('auditoria_contextos')->get(),
            DB::table('historico_acessos')->get(), DB::table('operacoes_integracao')->get(), DB::table('integracao_ocorrencias')->get(),
        ]);
        $this->assertStringNotContainsString(self::SENHA, $tudo);
        $this->assertStringNotContainsString($equipamento->endereco_rede, json_encode(DB::table('historico_acessos')->get()));
    }
}
