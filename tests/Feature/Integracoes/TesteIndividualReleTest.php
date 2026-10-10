<?php

namespace Tests\Feature\Integracoes;

use App\Integracoes\Adaptadores\Simulador\CenarioSimulador;
use App\Integracoes\Aplicacao\ComandosAbertura;
use App\Integracoes\Aplicacao\FabricaContextoEquipamento;
use App\Integracoes\Aplicacao\TesteReleBancada;
use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\EstadoSaude;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Integracoes\Dominio\Excecoes\PayloadDivergente;
use App\Models\AuditoriaEvento;
use App\Models\Equipamento;
use App\Models\Implantacao;
use App\Models\User;
use App\Support\ImplantacaoContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\Feature\Integracoes\Concerns\RespostasIsapi;
use Tests\TestCase;

/**
 * Teste individual do relé de cada facial: uma facial escolhida aciona
 * somente o seu relé, com o seu endereço, credencial e porta.
 */
class TesteIndividualReleTest extends TestCase
{
    use MontaCenarioIntegracao, RefreshDatabase, RespostasIsapi;

    private const SENHA_A = 'Senha-Facial-A-111';

    private const SENHA_B = 'Senha-Facial-B-222';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararIntegracao();
        config(['integracoes.teste_rele_habilitado' => true]);
    }

    protected function tearDown(): void
    {
        $this->encerrarIntegracao();
        parent::tearDown();
    }

    private function operador(): User
    {
        return $this->usuarioCom('integracoes.gerenciar', ComandosAbertura::PERMISSAO_LIBERAR);
    }

    private function testar(Equipamento $equipamento, ?User $ator = null, ?string $chave = null)
    {
        return $this->processar(app(TesteReleBancada::class)->solicitar(
            $equipamento, $chave ?? 'rele:'.Str::uuid7(), $ator ?? $this->operador(), 'Teste do relé na bancada',
        ));
    }

    /** @return array{0: Equipamento, 1: Equipamento} */
    private function duasFaciais(): array
    {
        $a = $this->terminalEmHomologacao(['nome' => 'Facial A', 'endereco_rede' => '192.168.50.11'], self::SENHA_A);
        $b = $this->terminalEmHomologacao(['nome' => 'Facial B', 'endereco_rede' => '192.168.50.12'], self::SENHA_B);
        $this->cadastro()->atualizarInventario($b, ['porta_rele' => 2], $b->versao);

        return [$a->refresh(), $b->refresh()];
    }

    // ---- Uma facial, um relé ---------------------------------------------------

    public function test_facial_a_aciona_somente_o_rele_da_facial_a_e_b_somente_o_de_b(): void
    {
        [$a, $b] = $this->duasFaciais();

        $this->testar($a);
        $this->assertSame(1, $this->simulador()->totalAberturasFisicas($a->id));
        $this->assertSame(0, $this->simulador()->totalAberturasFisicas($b->id));

        $this->testar($b);
        $this->assertSame(1, $this->simulador()->totalAberturasFisicas($a->id));
        $this->assertSame(1, $this->simulador()->totalAberturasFisicas($b->id));
    }

    public function test_contexto_da_facial_a_nunca_usa_endereco_credencial_ou_porta_da_b(): void
    {
        [$a, $b] = $this->duasFaciais();
        $fabrica = app(FabricaContextoEquipamento::class);

        $contextoA = $fabrica->para($a, (string) Str::uuid7());
        $contextoB = $fabrica->para($b, (string) Str::uuid7());

        $this->assertSame('192.168.50.11', $contextoA->enderecoRede);
        $this->assertSame(self::SENHA_A, $contextoA->segredo()->revelar());
        $this->assertSame(1, $contextoA->portaRele);
        $this->assertSame('192.168.50.12', $contextoB->enderecoRede);
        $this->assertSame(self::SENHA_B, $contextoB->segredo()->revelar());
        $this->assertSame(2, $contextoB->portaRele);
    }

    public function test_comando_isapi_vai_so_para_o_endereco_e_a_porta_da_facial_escolhida(): void
    {
        config(['integracoes.hikvision.perfis_homologados' => ['V-TESTE-1' => [
            'testar_conexao' => 'homologada', 'consultar_informacoes' => 'homologada',
            'consultar_capacidades' => 'homologada', 'abertura_remota' => 'homologada',
        ]]]);
        $this->fingirTerminal('V-TESTE-1', [
            '*/ISAPI/AccessControl/RemoteControl/door/*' => Http::response($this->xmlStatus(1, 'ok'), 200),
        ]);
        $a = $this->terminalEmHomologacao(['nome' => 'Facial A', 'endereco_rede' => '192.168.50.11', 'adaptador' => 'hikvision-isapi'], self::SENHA_A);
        $b = $this->terminalEmHomologacao(['nome' => 'Facial B', 'endereco_rede' => '192.168.50.12', 'adaptador' => 'hikvision-isapi'], self::SENHA_B);
        $this->cadastro()->atualizarInventario($b, ['porta_rele' => 2], $b->versao);

        $operacao = $this->testar($b->refresh());

        $this->assertSame(ResultadoEquipamento::Aceito, $operacao->resultado);
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_starts_with($r->url(), 'https://192.168.50.12') && str_ends_with($r->url(), '/ISAPI/AccessControl/RemoteControl/door/2'));
        Http::assertNotSent(fn (Request $r) => $r->method() === 'PUT' && str_contains($r->url(), '192.168.50.11'));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), self::SENHA_A) || str_contains($r->url(), self::SENHA_B));
        $this->assertNotNull($a);
    }

    // ---- Requisitos de segurança --------------------------------------------

    public function test_bloqueios_impedem_o_envio_e_explicam_o_motivo(): void
    {
        $casos = [
            'equipamento_offline' => fn (Equipamento $e) => $e->forceFill(['estado_saude' => EstadoSaude::Indisponivel])->save(),
            'equipamento_fora_de_homologacao' => fn (Equipamento $e) => $this->cadastro()->alterarStatus($e, StatusEquipamento::Manutencao, 'teste'),
            'certificado_nao_confiavel' => fn (Equipamento $e) => $e->forceFill(['tls_pin_sha256' => null])->save(),
            'firmware_nao_homologado' => fn (Equipamento $e) => $e->capacidades()->where('capacidade', Capacidade::AberturaRemota->value)->update(['estado_homologacao' => 'detectada']),
            'capacidade_ausente' => fn (Equipamento $e) => $e->capacidades()->where('capacidade', Capacidade::AberturaRemota->value)->update(['suportada' => false]),
        ];

        foreach ($casos as $codigo => $preparar) {
            $equipamento = $this->terminalEmHomologacao();
            $preparar($equipamento);

            $bloqueios = app(TesteReleBancada::class)->bloqueios($equipamento->refresh(), $this->operador());
            $this->assertArrayHasKey($codigo, $bloqueios, $codigo);
            $this->assertRegraViolada($codigo, fn () => $this->testar($equipamento));
            $this->assertSame(0, $this->simulador()->totalAberturasFisicas($equipamento->id), $codigo);
        }
    }

    public function test_usuario_sem_permissao_e_flag_desligada_sao_recusados(): void
    {
        $equipamento = $this->terminalEmHomologacao();

        $this->assertRegraViolada('sem_permissao', fn () => $this->testar($equipamento, $this->usuarioCom('integracoes.gerenciar')));

        config(['integracoes.teste_rele_habilitado' => false]);
        $this->assertRegraViolada('teste_rele_desabilitado', fn () => $this->testar($equipamento));
        $this->assertSame(0, $this->simulador()->totalAberturasFisicas($equipamento->id));
    }

    public function test_equipamento_de_outra_implantacao_nao_e_acionado(): void
    {
        $equipamento = $this->terminalEmHomologacao();

        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        $intruso = $this->operador();

        try {
            $this->testar($equipamento, $intruso);
            $this->fail('Equipamento de outra implantação foi acionado.');
        } catch (ModelNotFoundException) {
            $this->assertSame(0, $this->simulador()->totalAberturasFisicas($equipamento->id));
        } finally {
            ImplantacaoContext::setCurrentForTesting($this->implantacao);
        }
    }

    // ---- Idempotência ----------------------------------------------------------

    public function test_mesma_chave_e_mesmo_conteudo_devolve_a_mesma_operacao(): void
    {
        $equipamento = $this->terminalEmHomologacao();
        $operador = $this->operador();
        $chave = 'rele:'.Str::uuid7();

        $primeira = $this->testar($equipamento, $operador, $chave);
        $segunda = $this->testar($equipamento, $operador, $chave);

        $this->assertSame($primeira->id, $segunda->id);
        $this->assertSame(1, $this->simulador()->totalAberturasFisicas($equipamento->id));
    }

    public function test_mesma_chave_para_outra_facial_e_recusada(): void
    {
        [$a, $b] = $this->duasFaciais();
        $operador = $this->operador();
        $chave = 'rele:'.Str::uuid7();

        $this->testar($a, $operador, $chave);

        $this->expectException(PayloadDivergente::class);
        try {
            $this->testar($b, $operador, $chave);
        } finally {
            $this->assertSame(0, $this->simulador()->totalAberturasFisicas($b->id));
        }
    }

    // ---- Resultados ------------------------------------------------------------

    public function test_resultados_aceito_recusado_timeout_e_desconhecido(): void
    {
        $casos = [
            [CenarioSimulador::Aceito, ResultadoEquipamento::Aceito, 'aceito'],
            [CenarioSimulador::Recusa, ResultadoEquipamento::Recusado, 'recusado'],
            [CenarioSimulador::Timeout, ResultadoEquipamento::ConfirmacaoDesconhecida, 'desconhecido'],
            [CenarioSimulador::ConfirmacaoDesconhecida, ResultadoEquipamento::ConfirmacaoDesconhecida, 'desconhecido'],
        ];

        foreach ($casos as [$cenario, $resultado, $auditoria]) {
            $equipamento = $this->terminalEmHomologacao();
            $this->simulador()->definirCenario($equipamento->id, $cenario);

            $operacao = $this->testar($equipamento);

            $this->assertSame($resultado, $operacao->resultado, $cenario->value);
            $this->assertSame(1, $operacao->tentativas, $cenario->value);
            $this->assertSame($auditoria, AuditoriaEvento::query()->where('action', 'integracao_abertura_remota')->where('entity_id', $equipamento->id)->value('result'), $cenario->value);
        }
    }

    // ---- Auditoria -----------------------------------------------------------

    public function test_auditoria_completa_e_sem_dados_sensiveis(): void
    {
        $equipamento = $this->terminalEmHomologacao(['endereco_rede' => '192.168.50.31'], self::SENHA_A);
        $operador = $this->operador();
        $this->actingAs($operador);
        $chave = 'rele:'.Str::uuid7();

        $operacao = $this->testar($equipamento, $operador, $chave);

        $solicitacao = AuditoriaEvento::query()->where('action', 'liberacao_remota_solicitada')->with('context')->sole();
        $meta = $solicitacao->context->metadata;
        $this->assertSame($operador->id, $solicitacao->actor_id);
        $this->assertSame($this->implantacao->id, $solicitacao->implantacao_id);
        $this->assertSame($equipamento->id, $solicitacao->entity_id);
        $this->assertSame('Teste do relé na bancada', $solicitacao->justification);
        $this->assertNotNull($solicitacao->occurred_at);
        $this->assertSame($equipamento->pontoVigente()->id, $meta['ponto_acesso_id']);
        $this->assertSame(1, $meta['porta_rele']);
        $this->assertSame($chave, $meta['chave_idempotencia']);
        $this->assertSame($operacao->correlation_id, $meta['correlation_id']);

        $resultado = AuditoriaEvento::query()->where('action', 'integracao_abertura_remota')->with('context')->sole();
        $this->assertArrayHasKey('latencia_ms', $resultado->context->metadata);
        $this->assertSame($chave, $resultado->context->metadata['chave_idempotencia']);
        $this->assertSame($operacao->correlation_id, $resultado->context->metadata['correlation_id']);

        $tudo = json_encode([
            DB::table('auditoria_eventos')->get(), DB::table('auditoria_alteracoes')->get(), DB::table('auditoria_contextos')->get(),
            DB::table('operacoes_integracao')->get(), DB::table('integracao_ocorrencias')->get(),
        ]);
        $this->assertStringNotContainsString(self::SENHA_A, $tudo);
        $this->assertStringNotContainsString('192.168.50.31', json_encode(DB::table('auditoria_contextos')->get()));
        $this->assertFalse(AuditoriaEvento::query()->where('action', 'like', '%acesso_realizado%')->exists());
    }

    // ---- Homologação individual do relé -----------------------------------------

    public function test_rele_so_e_homologado_apos_teste_repetido_e_acionamento_observado(): void
    {
        $equipamento = $this->terminalEmHomologacao();
        $operador = $this->operador();
        $testes = app(TesteReleBancada::class);

        $this->testar($equipamento, $operador);
        $this->assertRegraViolada('testes_insuficientes', fn () => $testes->registrarHomologacao($equipamento, $operador, true, 'clique ouvido'));

        $this->testar($equipamento, $operador);
        $this->assertRegraViolada('acionamento_nao_observado', fn () => $testes->registrarHomologacao($equipamento, $operador, false, 'clique ouvido'));

        $homologado = $testes->registrarHomologacao($equipamento, $operador, true, 'Clique ouvido nos dois testes');
        $this->assertNotNull($homologado->rele_homologado_em);
        $this->assertSame($operador->id, $homologado->rele_homologado_por);
        $this->assertTrue(AuditoriaEvento::query()->where('action', 'equipamento_rele_homologado')->exists());

        // Trocar a porta exige homologar de novo.
        $alterado = $this->cadastro()->atualizarInventario($homologado, ['porta_rele' => 2], $homologado->versao);
        $this->assertNull($alterado->refresh()->rele_homologado_em);
    }

    public function test_endereco_e_mascarado_para_exibicao(): void
    {
        $equipamento = $this->novoEquipamento(['endereco_rede' => '192.168.50.8']);

        $this->assertSame('192.•••.•••.8', $equipamento->enderecoMascarado());
    }
}
