<?php

namespace Tests\Feature\Integracoes\Contrato;

use App\Integracoes\Adaptadores\Hikvision\HikvisionIsapiAdaptador;
use App\Integracoes\Dominio\Contratos\PortaEquipamentoAcesso;
use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;

/**
 * Enquanto o firmware do DS-K1T673DX-BR não for homologado, o adaptador
 * precisa ser honesto: nenhuma capacidade declarada, nenhuma operação
 * "bem-sucedida" e nenhum acesso à rede.
 */
class HikvisionContratoTest extends ContratoPortaEquipamentoTestCase
{
    protected function adaptador(): PortaEquipamentoAcesso
    {
        return new HikvisionIsapiAdaptador;
    }

    public function test_sem_firmware_homologado_nenhuma_capacidade_e_declarada(): void
    {
        $capacidades = $this->adaptador()->consultarCapacidades($this->contexto('V1.0.0 build 260901'));

        $this->assertSame([], $capacidades->suportadas);
        $this->assertFalse($capacidades->consultadoNoEquipamento);
        $this->assertStringContainsString('sem documentação ISAPI homologada', $capacidades->motivosAusencia[Capacidade::AberturaRemota->value]);
    }

    public function test_firmware_nao_inventariado_e_informado_como_motivo(): void
    {
        $resultado = $this->adaptador()->testarConexao($this->contexto(null));

        $this->assertSame(ResultadoEquipamento::CapacidadeAusente, $resultado->resultado);
        $this->assertStringContainsString('firmware não inventariado', (string) $resultado->mensagem);
    }

    public function test_perfil_homologado_nao_ativa_capacidade_que_o_codigo_ainda_nao_implementa(): void
    {
        config(['integracoes.hikvision.perfis_homologados' => ['V-HOMOLOGADO' => ['abertura_remota', 'sincronizar_credencial']]]);

        $capacidades = $this->adaptador()->consultarCapacidades($this->contexto('V-HOMOLOGADO'));

        $this->assertSame([], $capacidades->suportadas);
        $this->assertSame('capacidade ainda não implementada no adaptador', $capacidades->motivosAusencia['abertura_remota']);
    }

    public function test_todas_as_operacoes_declaram_capacidade_ausente(): void
    {
        foreach ($this->executarTodas($this->contexto()) as $operacao => $resultado) {
            if ($operacao === 'consultar_capacidades') {
                continue;
            }
            $this->assertSame(ResultadoEquipamento::CapacidadeAusente, $resultado->resultado, $operacao);
        }
    }
}
