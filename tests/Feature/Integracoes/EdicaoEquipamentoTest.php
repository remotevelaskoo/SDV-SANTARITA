<?php

namespace Tests\Feature\Integracoes;

use App\Integracoes\Aplicacao\DiagnosticoEquipamento;
use App\Integracoes\Dominio\Enums\EstadoSaude;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Integracoes\Dominio\Enums\StatusPontoAcesso;
use App\Livewire\EquipamentoManagement;
use App\Models\AuditoriaEvento;
use App\Models\Equipamento;
use App\Models\EquipamentoPontoVinculo;
use App\Models\Implantacao;
use App\Models\OperacaoIntegracao;
use App\Support\ImplantacaoContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\TestCase;

/** AJ-007 — edição segura de equipamentos (SDV-OPE-020 §14). Somente dados sintéticos. */
class EdicaoEquipamentoTest extends TestCase
{
    use MontaCenarioIntegracao, RefreshDatabase;

    private const SENHA = 'Senha-Tecnica-Sintetica-741';

    private const PIN = 'BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB=';

    private const AVISO = 'A comunicação com este equipamento poderá ficar indisponível até um novo teste de conexão. Nenhum histórico será apagado.';

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

    // ---- Serviço -------------------------------------------------------------

    public function test_troca_de_ip_mantem_o_mesmo_equipamento_e_exige_novo_teste(): void
    {
        $equipamento = $this->terminalHomologado();
        $operacoesAntes = OperacaoIntegracao::query()->where('equipamento_id', $equipamento->id)->count();
        $credencialAntes = $equipamento->credencialTecnicaAtiva()->id;
        $this->assertGreaterThan(0, $operacoesAntes);

        $resultado = $this->cadastro()->editarConfiguracao($equipamento, $this->dados($equipamento, ['endereco_rede' => '192.168.10.99']), null, $equipamento->versao);
        $editado = $resultado['equipamento'];

        $this->assertSame($equipamento->id, $editado->id, 'continua o mesmo UUID');
        $this->assertSame(1, Equipamento::query()->count());
        $this->assertSame('192.168.10.99', $editado->endereco_rede);
        $this->assertSame(EstadoSaude::Desconhecido, $editado->estado_saude, 'não testado');
        $this->assertNull($editado->tls_pin_sha256, 'certificado anterior não é mais confiado');
        $this->assertSame(StatusEquipamento::EmHomologacao, $editado->status);
        $this->assertTrue($resultado['conexao'] && $resultado['destino'] && $resultado['homologacao']);
        $this->assertSame(0, $editado->capacidades()->where('suportada', true)->count(), 'capacidades obtidas no endereço anterior deixam de valer');
        $this->assertGreaterThan(0, $editado->capacidades()->count(), 'capacidades continuam guardadas');

        // Histórico, operações e credencial preservados; nenhuma operação física nova.
        $this->assertSame($operacoesAntes, OperacaoIntegracao::query()->where('equipamento_id', $equipamento->id)->count());
        $this->assertSame($credencialAntes, $editado->credencialTecnicaAtiva()->id);
        $this->assertTrue(AuditoriaEvento::query()->where('entity_id', $equipamento->id)->where('action', 'equipamento_certificado_confiado')->exists());
    }

    public function test_mudanca_de_esquema_ou_porta_tambem_invalida_o_certificado(): void
    {
        foreach ([['porta_rede' => 8443], ['esquema' => 'http', 'porta_rede' => 80]] as $alteracao) {
            $equipamento = $this->terminalHomologado();
            $editado = $this->cadastro()->editarConfiguracao($equipamento, $this->dados($equipamento, $alteracao), null, $equipamento->versao)['equipamento'];

            $this->assertNull($editado->tls_pin_sha256);
            $this->assertSame(EstadoSaude::Desconhecido, $editado->estado_saude);
        }
    }

    public function test_mudanca_somente_do_nome_nao_invalida_conexao_nem_homologacao(): void
    {
        $equipamento = $this->terminalHomologado();
        $capacidadesAntes = $equipamento->capacidades()->where('suportada', true)->count();

        $resultado = $this->cadastro()->editarConfiguracao($equipamento, $this->dados($equipamento, ['nome' => 'Facial portaria norte']), null, $equipamento->versao);
        $editado = $resultado['equipamento'];

        $this->assertSame('Facial portaria norte', $editado->nome);
        $this->assertSame(StatusEquipamento::Ativo, $editado->status);
        $this->assertSame(EstadoSaude::Conectado, $editado->estado_saude);
        $this->assertSame(self::PIN, $editado->tls_pin_sha256);
        $this->assertSame($capacidadesAntes, $editado->capacidades()->where('suportada', true)->count());
        $this->assertFalse($resultado['conexao'] || $resultado['homologacao']);
    }

    public function test_troca_de_ponto_encerra_o_vinculo_vigente_e_cria_outro(): void
    {
        $equipamento = $this->terminalHomologado();
        $vinculoAnterior = $equipamento->vinculoVigente;
        $novoPonto = $this->novoPonto(['nome' => 'Catraca de pedestres', 'tipo' => 'catraca']);

        $editado = $this->cadastro()->editarConfiguracao($equipamento, $this->dados($equipamento), $novoPonto->id, $equipamento->versao)['equipamento'];

        $this->assertSame($novoPonto->id, $editado->pontoVigente()->id);
        $this->assertSame(StatusEquipamento::EmHomologacao, $editado->status);
        $this->assertSame(2, EquipamentoPontoVinculo::query()->where('equipamento_id', $equipamento->id)->count());
        $encerrado = $vinculoAnterior->fresh();
        $this->assertNotNull($encerrado->ended_at, 'vínculo anterior é encerrado, não sobrescrito');
        $this->assertSame($vinculoAnterior->ponto_acesso_id, $encerrado->ponto_acesso_id);
        $this->assertSame(EstadoSaude::Conectado, $editado->estado_saude, 'troca de ponto não muda a conexão');
    }

    public function test_ponto_de_outra_implantacao_ou_ja_atendido_e_recusado(): void
    {
        $equipamento = $this->terminalHomologado();
        $atual = ImplantacaoContext::current();
        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        $pontoAlheio = $this->novoPonto();
        ImplantacaoContext::setCurrentForTesting($atual);

        $this->assertRegraViolada('ponto_invalido', fn () => $this->cadastro()->editarConfiguracao($equipamento, $this->dados($equipamento), $pontoAlheio->id, $equipamento->versao));

        $ocupado = $this->terminalHomologado(['numero_serie' => 'SN-OUTRO-1'])->pontoVigente();
        $this->assertRegraViolada('ponto_ja_atendido', fn () => $this->cadastro()->editarConfiguracao($equipamento, $this->dados($equipamento), $ocupado->id, $equipamento->versao));

        $this->assertSame(1, EquipamentoPontoVinculo::query()->where('equipamento_id', $equipamento->id)->count());
    }

    public function test_valida_com_as_regras_do_cadastro_e_audita_a_recusa(): void
    {
        $equipamento = $this->terminalHomologado();

        $this->assertRegraViolada('endereco_publico', fn () => $this->cadastro()->editarConfiguracao($equipamento, $this->dados($equipamento, ['endereco_rede' => '8.8.8.8']), null, $equipamento->versao));
        $this->assertRegraViolada('porta_invalida', fn () => $this->cadastro()->editarConfiguracao($equipamento, $this->dados($equipamento, ['porta_rede' => 70000]), null, $equipamento->versao));
        $this->assertRegraViolada('adaptador_indisponivel', fn () => $this->cadastro()->editarConfiguracao($equipamento, $this->dados($equipamento, ['adaptador' => 'inexistente']), null, $equipamento->versao));

        $this->assertSame('192.168.10.20', $equipamento->fresh()->endereco_rede);
        $this->assertSame(3, AuditoriaEvento::query()->where('action', 'equipamento_edicao_recusada')->where('result', 'negado')->count());
        $this->assertDatabaseHas('auditoria_eventos', ['action' => 'equipamento_edicao_recusada', 'reason_code' => 'endereco_publico']);
    }

    public function test_detecta_edicao_concorrente(): void
    {
        $equipamento = $this->terminalHomologado();
        $versaoLida = $equipamento->versao;

        $this->cadastro()->editarConfiguracao($equipamento, $this->dados($equipamento, ['nome' => 'Primeira edição']), null, $versaoLida);

        $this->assertRegraViolada('versao_desatualizada', fn () => $this->cadastro()->editarConfiguracao($equipamento, $this->dados($equipamento, ['endereco_rede' => '192.168.10.77']), null, $versaoLida));
        $this->assertSame('192.168.10.20', $equipamento->fresh()->endereco_rede);
        $this->assertSame('Primeira edição', $equipamento->fresh()->nome);
    }

    public function test_auditoria_registra_campos_operador_e_valores_sem_segredo(): void
    {
        $admin = $this->usuarioCom('integracoes.gerenciar');
        $this->actingAs($admin);
        $equipamento = $this->terminalHomologado();

        $this->cadastro()->editarConfiguracao($equipamento, $this->dados($equipamento, ['endereco_rede' => '192.168.10.99', 'timeout_segundos' => 9]), null, $equipamento->versao, $admin);

        $evento = AuditoriaEvento::query()->where('action', 'equipamento_configuracao_alterada')->with(['changes', 'context'])->sole();
        $this->assertSame($admin->id, $evento->actor_id);
        $this->assertNotNull($evento->occurred_at);
        $alteracoes = $evento->changes->keyBy('field_name');
        $this->assertSame('192.168.10.20', $alteracoes['endereco_rede']->old_value);
        $this->assertSame('192.168.10.99', $alteracoes['endereco_rede']->new_value);
        $this->assertSame('5', (string) $alteracoes['timeout_segundos']->old_value);
        $this->assertSame('9', (string) $alteracoes['timeout_segundos']->new_value);
        $this->assertArrayHasKey('tls_pin_sha256', $alteracoes->all());
        $this->assertContains('certificado_reconfirmar', $evento->context->metadata['efeitos']);
        $this->assertContains('teste_conexao_pendente', $evento->context->metadata['efeitos']);

        $auditoria = json_encode(DB::table('auditoria_eventos')->get()).json_encode(DB::table('auditoria_alteracoes')->get()).json_encode(DB::table('auditoria_contextos')->get());
        $this->assertStringNotContainsString(self::SENHA, $auditoria);
        $this->assertStringNotContainsString(self::PIN, json_encode(DB::table('auditoria_alteracoes')->where('auditoria_evento_id', $evento->id)->get()));
    }

    public function test_equipamento_inativo_nao_e_editado(): void
    {
        $equipamento = $this->cadastro()->alterarStatus($this->terminalHomologado(), StatusEquipamento::Inativo, 'retirado');

        $this->assertRegraViolada('equipamento_inativo', fn () => $this->cadastro()->editarConfiguracao($equipamento, $this->dados($equipamento, ['nome' => 'Outro']), null, $equipamento->versao));
    }

    // ---- Tela ----------------------------------------------------------------

    public function test_tela_edita_ip_com_confirmacao_sem_pedir_nem_mostrar_a_senha(): void
    {
        $equipamento = $this->terminalHomologado();
        $operacoesAntes = OperacaoIntegracao::query()->count();

        $tela = Livewire::actingAs($this->usuarioCom('integracoes.gerenciar'))
            ->test(EquipamentoManagement::class)
            ->call('selecionar', $equipamento->id)
            ->assertSee('Editar equipamento')
            ->assertSee('Trocar credencial')
            ->call('iniciarEdicao')
            ->assertSet('edicao.endereco_rede', '192.168.10.20')
            ->assertDontSee('Senha técnica')
            ->assertDontSee(self::SENHA)
            ->set('edicao.endereco_rede', '192.168.10.99')
            ->call('revisarEdicao')
            ->assertHasNoErrors()
            ->assertSee('Valor atual')
            ->assertSee('Novo valor')
            ->assertSee(self::AVISO)
            ->assertSet('previaEdicao', [['campo' => 'endereco_rede', 'rotulo' => 'Endereço IP', 'atual' => '192.168.10.20', 'novo' => '192.168.10.99']])
            ->assertSee('<td>192.168.10.20</td>', escape: false);

        $this->assertSame('192.168.10.20', $equipamento->fresh()->endereco_rede, 'nada é salvo antes da confirmação');

        $tela->call('confirmarEdicao')
            ->assertSet('editando', false)
            ->assertSee('Teste novamente a conexão e, em HTTPS, confira o certificado apresentado.')
            ->assertSee('Conexão não testada')
            ->assertDontSee(self::SENHA);

        $this->assertSame('192.168.10.99', $equipamento->fresh()->endereco_rede);
        $this->assertSame($operacoesAntes, OperacaoIntegracao::query()->count(), 'salvar não testa conexão, não aciona relé nem sincroniza');
    }

    public function test_tela_salva_somente_o_nome_sem_confirmacao_de_conexao(): void
    {
        $equipamento = $this->terminalHomologado();

        Livewire::actingAs($this->usuarioCom('integracoes.gerenciar'))
            ->test(EquipamentoManagement::class)
            ->call('selecionar', $equipamento->id)
            ->call('iniciarEdicao')
            ->set('edicao.nome', 'Facial portaria sul')
            ->call('revisarEdicao')
            ->assertSet('previaEdicao', null)
            ->assertSet('editando', false)
            ->assertSee('Conexão e homologação não foram afetadas.')
            ->assertDontSee(self::AVISO);

        $this->assertSame('Facial portaria sul', $equipamento->fresh()->nome);
        $this->assertSame(StatusEquipamento::Ativo, $equipamento->fresh()->status);
    }

    public function test_tela_recarrega_quando_outro_administrador_salvou_antes(): void
    {
        $equipamento = $this->terminalHomologado();
        $tela = Livewire::actingAs($this->usuarioCom('integracoes.gerenciar'))
            ->test(EquipamentoManagement::class)
            ->call('selecionar', $equipamento->id)
            ->call('iniciarEdicao')
            ->set('edicao.endereco_rede', '192.168.10.99')
            ->call('revisarEdicao');

        $this->cadastro()->editarConfiguracao($equipamento, $this->dados($equipamento, ['nome' => 'Alterado por outra pessoa']), null, $equipamento->versao);

        $tela->call('confirmarEdicao')
            ->assertSee('Equipamento alterado por outra pessoa')
            ->assertSet('edicao.nome', 'Alterado por outra pessoa')
            ->assertSet('edicao.endereco_rede', '192.168.10.20');

        $this->assertSame('192.168.10.20', $equipamento->fresh()->endereco_rede);
    }

    public function test_tela_audita_dados_invalidos_sem_registrar_o_valor(): void
    {
        $equipamento = $this->terminalHomologado();

        Livewire::actingAs($this->usuarioCom('integracoes.gerenciar'))
            ->test(EquipamentoManagement::class)
            ->call('selecionar', $equipamento->id)
            ->call('iniciarEdicao')
            ->set('edicao.endereco_rede', 'nao-e-ip')
            ->call('revisarEdicao')
            ->assertHasErrors('edicao.endereco_rede');

        $evento = AuditoriaEvento::query()->where('action', 'equipamento_edicao_recusada')->with('context')->sole();
        $this->assertSame('dados_invalidos', $evento->reason_code);
        $this->assertSame(['endereco_rede'], $evento->context->metadata['campos']);
        $this->assertStringNotContainsString('nao-e-ip', json_encode($evento->context->metadata));
    }

    public function test_sem_permissao_nao_ve_nem_executa_a_edicao(): void
    {
        $equipamento = $this->terminalHomologado();

        $this->actingAs($this->usuarioCom('dashboard.visualizar'))->get('/equipamentos')->assertRedirect(route('dashboard'));
        Livewire::actingAs($this->usuarioCom('dashboard.visualizar'))
            ->test(EquipamentoManagement::class)
            ->assertForbidden();

        $this->assertSame('Terminal facial portaria', $equipamento->fresh()->nome);
    }

    public function test_equipamento_de_outra_implantacao_nao_abre_para_edicao(): void
    {
        $atual = ImplantacaoContext::current();
        $admin = $this->usuarioCom('integracoes.gerenciar');
        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        $alheio = $this->novoEquipamento();
        ImplantacaoContext::setCurrentForTesting($atual);

        $this->expectException(ModelNotFoundException::class);
        Livewire::actingAs($admin)
            ->test(EquipamentoManagement::class)
            ->set('mode', 'detail')
            ->set('selecionadoId', $alheio->id)
            ->call('iniciarEdicao');
    }

    // ---- Auxiliares ------------------------------------------------------------

    /** Terminal ativo, conectado, com certificado confiado, capacidades e credencial cifrada. */
    private function terminalHomologado(array $dados = []): Equipamento
    {
        $ponto = $this->novoPonto();
        $this->cadastro()->alterarStatusPonto($ponto, StatusPontoAcesso::Ativo, 'operação');
        $equipamento = $this->novoEquipamento($dados);
        $this->cadastro()->vincularAoPonto($equipamento, $ponto);
        $this->cadastro()->definirSenhaTecnica($equipamento, 'admin', self::SENHA);
        $this->cadastro()->confiarCertificado($equipamento, self::PIN, 'IMPRESSAO-SINTETICA');
        $this->processar(app(DiagnosticoEquipamento::class)->consultarCapacidades($equipamento->refresh()));
        $this->processar(app(DiagnosticoEquipamento::class)->testarConexao($equipamento->refresh()));

        return $this->cadastro()->alterarStatus($equipamento->refresh(), StatusEquipamento::Ativo, 'operação')->refresh();
    }

    /** @return array<string, mixed> */
    private function dados(Equipamento $equipamento, array $alteracoes = []): array
    {
        return array_merge([
            'nome' => $equipamento->nome,
            'endereco_rede' => $equipamento->endereco_rede,
            'esquema' => $equipamento->esquema,
            'porta_rede' => $equipamento->porta_rede,
            'timeout_segundos' => $equipamento->timeout_segundos,
            'protocolo' => $equipamento->protocolo,
            'adaptador' => $equipamento->adaptador,
            'direcao' => $equipamento->direcao->value,
            'modulo_seguro_rs485' => $equipamento->modulo_seguro_rs485,
        ], $alteracoes);
    }
}
