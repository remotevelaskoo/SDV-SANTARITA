<?php

namespace App\Integracoes\Adaptadores\Hikvision;

use App\Integracoes\Dominio\Contratos\PortaEquipamentoAcesso;
use App\Integracoes\Dominio\Dados\CapacidadesDeclaradas;
use App\Integracoes\Dominio\Dados\ComandoAbertura;
use App\Integracoes\Dominio\Dados\ContextoEquipamento;
use App\Integracoes\Dominio\Dados\CredencialParaSincronizar;
use App\Integracoes\Dominio\Dados\ResultadoColetaEventos;
use App\Integracoes\Dominio\Dados\ResultadoOperacao;
use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;

/**
 * Adaptador do terminal Hikvision DS-K1T673DX-BR (ADR-016), atrás da porta
 * de equipamentos (ADR-007).
 *
 * SITUAÇÃO: fundação, sem integração real. O protocolo esperado é ISAPI,
 * mas o firmware instalado e a documentação oficial compatível ainda não
 * foram confirmados (PEN-ADR-016-001 e PEN-ADR-016-002). Por isso este
 * adaptador NÃO contém endpoints, formatos de payload nem autenticação
 * ISAPI, e declara todas as capacidades como ausentes, com o motivo.
 *
 * Para habilitar uma capacidade será preciso, nesta ordem:
 * 1. inventariar o firmware do terminal em bancada;
 * 2. obter a documentação ISAPI oficial daquela versão;
 * 3. implementar a chamada correspondente neste namespace (nunca fora dele);
 * 4. registrar o firmware em `integracoes.hikvision.perfis_homologados`
 *    com as capacidades comprovadas;
 * 5. fazer este adaptador passar no teste de contrato da porta.
 *
 * Classes e payloads Hikvision ficam confinados a este namespace; o que
 * atravessa a porta são somente os tipos de App\Integracoes\Dominio.
 */
class HikvisionIsapiAdaptador implements PortaEquipamentoAcesso
{
    public const CODIGO = 'hikvision-isapi';

    public const VERSAO_CONTRATO = '0.1.0';

    /**
     * Capacidades que ESTE código já sabe executar. Uma capacidade só é
     * declarada quando está aqui E no perfil homologado do firmware.
     * Vazio até existir documentação oficial.
     *
     * @var list<Capacidade>
     */
    private const IMPLEMENTADAS = [];

    public function codigo(): string
    {
        return self::CODIGO;
    }

    public function versaoContrato(): string
    {
        return self::VERSAO_CONTRATO;
    }

    public function testarConexao(ContextoEquipamento $equipamento): ResultadoOperacao
    {
        return $this->ausente(Capacidade::TestarConexao, $equipamento);
    }

    public function consultarCapacidades(ContextoEquipamento $equipamento): CapacidadesDeclaradas
    {
        $perfil = $this->perfilDoFirmware($equipamento->firmwareVersao);
        $homologadas = array_map(fn (string $c) => Capacidade::from($c), $perfil ?? []);
        $suportadas = array_values(array_filter(
            self::IMPLEMENTADAS,
            fn (Capacidade $c) => in_array($c, $homologadas, true),
        ));

        $motivos = [];
        foreach (Capacidade::cases() as $capacidade) {
            if (! in_array($capacidade, $suportadas, true)) {
                $motivos[$capacidade->value] = $this->motivoAusencia($equipamento->firmwareVersao, $perfil !== null);
            }
        }

        return new CapacidadesDeclaradas(
            resultado: ResultadoEquipamento::Confirmado,
            versaoContrato: self::VERSAO_CONTRATO,
            firmwareVersao: $equipamento->firmwareVersao,
            suportadas: $suportadas,
            motivosAusencia: $motivos,
            mensagem: 'Capacidades declaradas pelo adaptador, sem consulta ao equipamento.',
        );
    }

    public function sincronizarCredencial(ContextoEquipamento $equipamento, CredencialParaSincronizar $credencial): ResultadoOperacao
    {
        return $this->ausente(Capacidade::SincronizarCredencial, $equipamento);
    }

    public function revogarCredencial(ContextoEquipamento $equipamento, string $credencialId, ?string $idExterno): ResultadoOperacao
    {
        return $this->ausente(Capacidade::RevogarCredencial, $equipamento);
    }

    public function consultarSincronizacao(ContextoEquipamento $equipamento, string $credencialId, ?string $idExterno): ResultadoOperacao
    {
        return $this->ausente(Capacidade::ConsultarSincronizacao, $equipamento);
    }

    public function coletarEventos(ContextoEquipamento $equipamento, ?string $cursor): ResultadoColetaEventos
    {
        return new ResultadoColetaEventos(
            ResultadoEquipamento::CapacidadeAusente,
            mensagem: Capacidade::ColetarEventos->value.': '.$this->motivoAusencia($equipamento->firmwareVersao, false),
        );
    }

    public function solicitarAbertura(ContextoEquipamento $equipamento, ComandoAbertura $comando): ResultadoOperacao
    {
        return $this->ausente(Capacidade::AberturaRemota, $equipamento);
    }

    public function consultarResultadoComando(ContextoEquipamento $equipamento, string $comandoId): ResultadoOperacao
    {
        return $this->ausente(Capacidade::ConsultarResultadoComando, $equipamento);
    }

    private function ausente(Capacidade $capacidade, ContextoEquipamento $equipamento): ResultadoOperacao
    {
        return ResultadoOperacao::capacidadeAusente(
            $capacidade,
            $this->motivoAusencia($equipamento->firmwareVersao, $this->perfilDoFirmware($equipamento->firmwareVersao) !== null),
        );
    }

    /** @return list<string>|null */
    private function perfilDoFirmware(?string $firmware): ?array
    {
        if ($firmware === null) {
            return null;
        }

        $perfis = (array) config('integracoes.hikvision.perfis_homologados');

        return isset($perfis[$firmware]) ? array_values((array) $perfis[$firmware]) : null;
    }

    private function motivoAusencia(?string $firmware, bool $perfilExiste): string
    {
        return match (true) {
            $firmware === null => 'firmware não inventariado',
            ! $perfilExiste => "firmware {$firmware} sem documentação ISAPI homologada",
            default => 'capacidade ainda não implementada no adaptador',
        };
    }
}
