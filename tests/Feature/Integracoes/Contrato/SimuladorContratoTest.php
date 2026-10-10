<?php

namespace Tests\Feature\Integracoes\Contrato;

use App\Integracoes\Adaptadores\Simulador\CenarioSimulador;
use App\Integracoes\Adaptadores\Simulador\SimuladorEquipamento;
use App\Integracoes\Dominio\Contratos\PortaEquipamentoAcesso;
use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\OperacaoIntegracao;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;

class SimuladorContratoTest extends ContratoPortaEquipamentoTestCase
{
    private SimuladorEquipamento $simulador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simulador = new SimuladorEquipamento;
    }

    protected function adaptador(): PortaEquipamentoAcesso
    {
        return $this->simulador;
    }

    public function test_timeout_devolve_confirmacao_desconhecida_e_nao_sucesso(): void
    {
        $contexto = $this->contexto();
        $this->simulador->definirCenario($contexto->equipamentoId, CenarioSimulador::Timeout);

        foreach ($this->executarTodas($contexto) as $operacao => $resultado) {
            if (in_array($operacao, ['consultar_capacidades', 'coletar_eventos'], true)) {
                continue;
            }
            $this->assertSame(ResultadoEquipamento::ConfirmacaoDesconhecida, $resultado->resultado, $operacao);
        }
    }

    public function test_indisponibilidade_e_recusa_sao_explicitas(): void
    {
        $contexto = $this->contexto();

        $this->simulador->definirCenario($contexto->equipamentoId, CenarioSimulador::Indisponivel);
        $this->assertSame(ResultadoEquipamento::Indisponivel, $this->simulador->testarConexao($contexto)->resultado);

        $this->simulador->definirCenario($contexto->equipamentoId, CenarioSimulador::Recusa);
        $this->assertSame(ResultadoEquipamento::Recusado, $this->simulador->sincronizarCredencial($contexto, $this->credencial())->resultado);
    }

    public function test_capacidade_retirada_passa_a_ser_ausente(): void
    {
        $contexto = $this->contexto();
        $this->simulador->definirCapacidades($contexto->equipamentoId, [Capacidade::TestarConexao]);

        $this->assertFalse($this->simulador->consultarCapacidades($contexto)->suporta(Capacidade::AberturaRemota));
        $this->assertSame(ResultadoEquipamento::CapacidadeAusente, $this->simulador->solicitarAbertura($contexto, $this->comando($contexto))->resultado);
        $this->assertSame(0, $this->simulador->totalAberturasFisicas($contexto->equipamentoId));
    }

    public function test_callback_tardio_aplica_efeito_mas_devolve_desconhecido(): void
    {
        $contexto = $this->contexto();
        $credencial = $this->credencial();
        $this->simulador->definirCenario($contexto->equipamentoId, CenarioSimulador::CallbackTardio, OperacaoIntegracao::SincronizarCredencial);

        $this->assertSame(ResultadoEquipamento::ConfirmacaoDesconhecida, $this->simulador->sincronizarCredencial($contexto, $credencial)->resultado);
        $this->assertArrayHasKey($credencial->credencialId, $this->simulador->credenciaisGravadas($contexto->equipamentoId));
        $this->assertTrue($this->simulador->consultarSincronizacao($contexto, $credencial->credencialId, null)->dados['presente']);
    }

    public function test_eventos_duplicados_e_invalidos(): void
    {
        $contexto = $this->contexto();
        $this->simulador->registrarEventoNoTerminal($contexto->equipamentoId, ['serial' => '1001', 'tipo' => 'reconhecimento', 'instante' => '2026-09-27T10:00:00-03:00']);
        $this->simulador->registrarEventoNoTerminal($contexto->equipamentoId, ['tipo' => 'sem_serial']);
        $this->simulador->definirCenario($contexto->equipamentoId, CenarioSimulador::Duplicidade, OperacaoIntegracao::ColetarEventos);

        $coleta = $this->simulador->coletarEventos($contexto, null);

        $this->assertCount(2, $coleta->eventos);
        $this->assertSame('1001', $coleta->eventos[0]->idExterno);
        $this->assertSame(2, $coleta->descartadosInvalidos);
    }
}
