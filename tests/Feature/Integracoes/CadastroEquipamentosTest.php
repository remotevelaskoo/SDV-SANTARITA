<?php

namespace Tests\Feature\Integracoes;

use App\Integracoes\Aplicacao\DiagnosticoEquipamento;
use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Integracoes\Dominio\Enums\StatusPontoAcesso;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\AuditoriaAlteracao;
use App\Models\AuditoriaEvento;
use App\Models\Equipamento;
use App\Models\EquipamentoCredencial;
use App\Models\EquipamentoPontoVinculo;
use App\Models\Implantacao;
use App\Support\ImplantacaoContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\TestCase;

class CadastroEquipamentosTest extends TestCase
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

    public function test_cadastra_equipamento_com_inventario_e_estados_iniciais(): void
    {
        $equipamento = $this->novoEquipamento(['firmware_versao' => 'V1.2.3']);

        $this->assertSame($this->implantacao->id, $equipamento->implantacao_id);
        $this->assertSame(StatusEquipamento::NaoConfigurado, $equipamento->status);
        $this->assertSame('desconhecido', $equipamento->estado_saude->value);
        $this->assertNull($equipamento->ultima_comunicacao_at);
        $this->assertSame('DS-K1T673DX-BR', $equipamento->modelo);
        $this->assertTrue(AuditoriaEvento::query()->where('action', 'equipamento_cadastrado')->where('entity_id', $equipamento->id)->exists());
    }

    public function test_recusa_ip_publico_e_nome_de_host(): void
    {
        foreach (['8.8.8.8', 'terminal.exemplo.com'] as $endereco) {
            try {
                $this->novoEquipamento(['endereco_rede' => $endereco]);
                $this->fail("Endereço aceito indevidamente: {$endereco}");
            } catch (RegraIntegracaoViolada $e) {
                $this->assertContains($e->codigo, ['endereco_publico', 'endereco_invalido']);
            }
        }

        $this->assertSame(0, Equipamento::query()->count());
    }

    public function test_simulador_nao_e_aceito_quando_desabilitado(): void
    {
        config(['integracoes.simulador_permitido' => false]);

        $this->assertRegraViolada('adaptador_indisponivel', fn () => $this->novoEquipamento());
    }

    public function test_numero_de_serie_e_unico_na_implantacao_mas_nao_entre_implantacoes(): void
    {
        $this->novoEquipamento(['numero_serie' => 'SERIE-1']);

        try {
            $this->novoEquipamento(['numero_serie' => 'SERIE-1']);
            $this->fail('Série duplicada aceita.');
        } catch (RegraIntegracaoViolada $e) {
            $this->assertSame('serie_duplicada', $e->codigo);
        }

        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        $this->assertSame('SERIE-1', $this->novoEquipamento(['numero_serie' => 'SERIE-1'])->numero_serie);
    }

    public function test_equipamento_de_outra_implantacao_nao_e_visivel(): void
    {
        $equipamento = $this->novoEquipamento();

        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());

        $this->assertNull(Equipamento::query()->find($equipamento->id));
    }

    public function test_vinculo_entre_terminal_e_ponto_e_exclusivo_nos_dois_sentidos(): void
    {
        $ponto = $this->novoPonto();
        $outroPonto = $this->novoPonto();
        $terminal = $this->novoEquipamento();
        $outroTerminal = $this->novoEquipamento();

        $this->cadastro()->vincularAoPonto($terminal, $ponto);

        foreach ([[$terminal, $outroPonto, 'equipamento_ja_vinculado'], [$outroTerminal, $ponto, 'ponto_ja_atendido']] as [$eq, $pt, $codigo]) {
            try {
                $this->cadastro()->vincularAoPonto($eq, $pt);
                $this->fail("Vínculo duplicado aceito: {$codigo}");
            } catch (RegraIntegracaoViolada $e) {
                $this->assertSame($codigo, $e->codigo);
            }
        }

        $this->assertSame(1, EquipamentoPontoVinculo::query()->whereNull('ended_at')->count());
    }

    public function test_indice_do_banco_impede_dois_vinculos_vigentes_mesmo_sem_a_regra_da_aplicacao(): void
    {
        $ponto = $this->novoPonto();
        $a = $this->novoEquipamento();
        $b = $this->novoEquipamento();
        EquipamentoPontoVinculo::query()->create(['equipamento_id' => $a->id, 'ponto_acesso_id' => $ponto->id, 'started_at' => now()]);

        $this->expectException(UniqueConstraintViolationException::class);
        DB::transaction(fn () => EquipamentoPontoVinculo::query()->create(['equipamento_id' => $b->id, 'ponto_acesso_id' => $ponto->id, 'started_at' => now()]));
    }

    public function test_desvincular_preserva_historico_e_libera_o_ponto(): void
    {
        $ponto = $this->novoPonto();
        $terminal = $this->novoEquipamento();
        $this->cadastro()->vincularAoPonto($terminal, $ponto);

        $encerrado = $this->cadastro()->desvincularDoPonto($terminal, 'troca de terminal');
        $novo = $this->novoEquipamento();
        $this->cadastro()->vincularAoPonto($novo, $ponto);

        $this->assertNotNull($encerrado->refresh()->ended_at);
        $this->assertSame('troca de terminal', $encerrado->motivo_encerramento);
        $this->assertSame(2, EquipamentoPontoVinculo::query()->where('ponto_acesso_id', $ponto->id)->count());
        $this->assertSame($novo->id, $ponto->refresh()->vinculoVigente->equipamento_id);
    }

    public function test_direcao_do_terminal_deve_ser_suportada_pelo_ponto(): void
    {
        $ponto = $this->novoPonto(['direcao_suportada' => 'saida']);
        $terminal = $this->novoEquipamento(['direcao' => 'entrada']);

        $this->assertRegraViolada('direcao_incompativel', fn () => $this->cadastro()->vincularAoPonto($terminal, $ponto));
    }

    public function test_ponto_aceita_somente_cancela_ou_catraca(): void
    {
        $this->assertRegraViolada('tipo_ponto_invalido', fn () => $this->novoPonto(['tipo' => 'porta']));
    }

    public function test_credencial_tecnica_guarda_somente_referencia_e_nunca_vai_ao_frontend(): void
    {
        $terminal = $this->novoEquipamento();

        foreach (['Senha-Terminal-Teste-987', 'admin:12345', 'env:minuscula'] as $invalida) {
            try {
                $this->cadastro()->definirCredencialTecnica($terminal, $invalida);
                $this->fail("Valor aceito como referência: {$invalida}");
            } catch (RegraIntegracaoViolada $e) {
                $this->assertSame('referencia_segredo_invalida', $e->codigo);
            }
        }

        $credencial = $this->cadastro()->definirCredencialTecnica($terminal, 'env:SDV_TESTE_TERMINAL_SENHA', 'admin');

        $this->assertSame('env:SDV_TESTE_TERMINAL_SENHA', $credencial->referencia_segredo);
        $this->assertArrayNotHasKey('referencia_segredo', $credencial->toArray());
        $this->assertStringNotContainsString('SDV_TESTE_TERMINAL_SENHA', $terminal->load('credenciaisTecnicas')->toJson());
        $this->assertDatabaseMissing('equipamento_credenciais', ['referencia_segredo' => 'Senha-Terminal-Teste-987']);
        $this->assertFalse(AuditoriaAlteracao::query()->where('new_value', 'like', '%SDV_TESTE_TERMINAL_SENHA%')->exists());
    }

    public function test_substituir_credencial_preserva_a_anterior(): void
    {
        $terminal = $this->novoEquipamento();
        $primeira = $this->cadastro()->definirCredencialTecnica($terminal, 'env:SDV_TERMINAL_A');
        $segunda = $this->cadastro()->definirCredencialTecnica($terminal, 'env:SDV_TERMINAL_B');

        $this->assertSame('substituida', $primeira->refresh()->status);
        $this->assertNotNull($primeira->substituida_em);
        $this->assertSame($segunda->id, $terminal->credencialTecnicaAtiva()->id);
        $this->assertSame(2, EquipamentoCredencial::query()->count());
    }

    public function test_ativacao_exige_ponto_credencial_capacidades_e_firmware(): void
    {
        $terminal = $this->novoEquipamento(['adaptador' => 'hikvision-isapi', 'firmware_versao' => null]);
        $ativar = fn () => $this->cadastro()->alterarStatus($terminal->refresh(), StatusEquipamento::Ativo, 'teste');

        $this->assertRegraViolada('sem_ponto', $ativar);

        $this->cadastro()->vincularAoPonto($terminal, $this->novoPonto());
        $this->assertRegraViolada('sem_credencial_tecnica', $ativar);

        $this->cadastro()->definirCredencialTecnica($terminal, 'env:SDV_TESTE_TERMINAL_SENHA');
        $this->assertRegraViolada('capacidades_nao_verificadas', $ativar);

        $this->processar(app(DiagnosticoEquipamento::class)->consultarCapacidades($terminal));
        $this->assertRegraViolada('firmware_nao_inventariado', $ativar);

        $this->cadastro()->atualizarInventario($terminal->refresh(), ['firmware_versao' => 'V1.0.0'], $terminal->versao);
        $this->assertSame(StatusEquipamento::Ativo, $ativar()->status);
    }

    public function test_troca_de_firmware_invalida_capacidades_e_exige_versao_atual(): void
    {
        $terminal = $this->terminalAtivo();
        $this->assertTrue($terminal->suporta(Capacidade::SincronizarCredencial));

        try {
            $this->cadastro()->atualizarInventario($terminal, ['firmware_versao' => 'V-TESTE-2'], $terminal->versao - 1);
            $this->fail('Versão desatualizada aceita.');
        } catch (RegraIntegracaoViolada $e) {
            $this->assertSame('versao_desatualizada', $e->codigo);
        }

        $atualizado = $this->cadastro()->atualizarInventario($terminal, ['firmware_versao' => 'V-TESTE-2', 'modelo' => 'ignorado'], $terminal->versao);

        $this->assertSame('V-TESTE-2', $atualizado->firmware_versao);
        $this->assertSame('DS-K1T673DX-BR', $atualizado->modelo);
        $this->assertFalse($atualizado->suporta(Capacidade::SincronizarCredencial));
        $this->assertTrue(AuditoriaEvento::query()->where('action', 'equipamento_inventario_alterado')->exists());
    }

    public function test_inativar_equipamento_encerra_vinculo_sem_apagar(): void
    {
        $terminal = $this->terminalAtivo();
        $vinculo = $terminal->vinculoVigente;

        $this->cadastro()->alterarStatus($terminal, StatusEquipamento::Inativo, 'descarte');

        $this->assertNotNull($vinculo->refresh()->ended_at);
        $this->assertSame(StatusEquipamento::Inativo, $terminal->refresh()->status);
        $this->assertNotNull($terminal->inactivated_at);
        $this->assertDatabaseHas('equipamentos', ['id' => $terminal->id]);

        $this->assertRegraViolada('equipamento_inativo', fn () => $this->cadastro()->alterarStatus($terminal->refresh(), StatusEquipamento::Ativo, 'volta'));
    }

    public function test_ponto_inativo_nao_recebe_terminal(): void
    {
        $ponto = $this->novoPonto();
        $this->cadastro()->alterarStatusPonto($ponto, StatusPontoAcesso::Inativo, 'obra');

        $this->assertRegraViolada('ponto_inativo', fn () => $this->cadastro()->vincularAoPonto($this->novoEquipamento(), $ponto->refresh()));
    }

    public function test_hikvision_sem_firmware_homologado_nao_declara_capacidades(): void
    {
        $terminal = $this->novoEquipamento(['adaptador' => 'hikvision-isapi']);

        $this->processar(app(DiagnosticoEquipamento::class)->consultarCapacidades($terminal));
        $teste = $this->processar(app(DiagnosticoEquipamento::class)->testarConexao($terminal));

        $this->assertSame(0, $terminal->capacidades()->where('suportada', true)->count());
        $this->assertSame(count(Capacidade::cases()), $terminal->capacidades()->count());
        $this->assertSame('capacidade_ausente', $teste->resultado->value);
        $this->assertNull($terminal->refresh()->ultima_comunicacao_at);
        $this->assertSame('desconhecido', $terminal->estado_saude->value);
    }
}
