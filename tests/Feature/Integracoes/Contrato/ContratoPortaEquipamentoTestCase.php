<?php

namespace Tests\Feature\Integracoes\Contrato;

use App\Integracoes\Dominio\Contratos\PortaEquipamentoAcesso;
use App\Integracoes\Dominio\Dados\CapacidadesDeclaradas;
use App\Integracoes\Dominio\Dados\ComandoAbertura;
use App\Integracoes\Dominio\Dados\ContextoEquipamento;
use App\Integracoes\Dominio\Dados\CredencialParaSincronizar;
use App\Integracoes\Dominio\Dados\ResultadoColetaEventos;
use App\Integracoes\Dominio\Dados\ResultadoOperacao;
use App\Integracoes\Dominio\Dados\SegredoTecnico;
use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\Direcao;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Teste de contrato da porta de equipamentos (ADR-007 §18). Todo adaptador
 * deve passar por esta suíte: o simulador agora, o Hikvision real quando
 * houver firmware homologado.
 */
abstract class ContratoPortaEquipamentoTestCase extends TestCase
{
    protected const SEGREDO = 'Segredo-Contrato-Nao-Pode-Vazar-42';

    abstract protected function adaptador(): PortaEquipamentoAcesso;

    protected function contexto(?string $firmware = 'V-CONTRATO-1'): ContextoEquipamento
    {
        return new ContextoEquipamento(
            implantacaoId: (string) Str::uuid7(),
            equipamentoId: (string) Str::uuid7(),
            pontoAcessoId: (string) Str::uuid7(),
            fabricante: 'Fabricante de teste',
            modelo: 'Modelo de teste',
            firmwareVersao: $firmware,
            enderecoRede: '192.168.50.10',
            portaRede: 443,
            protocolo: 'teste',
            direcao: Direcao::Entrada,
            timeoutSegundos: 2,
            usuarioTecnico: 'admin',
            obterSegredo: fn () => new SegredoTecnico(self::SEGREDO),
            correlationId: (string) Str::uuid7(),
        );
    }

    protected function credencial(): CredencialParaSincronizar
    {
        return new CredencialParaSincronizar(
            credencialId: (string) Str::uuid7(),
            tipo: 'cartao',
            titularReferencia: (string) Str::uuid7(),
            nomeExibicao: 'Pessoa Sintética',
            vigenciaInicio: new DateTimeImmutable('-1 day'),
            vigenciaFim: new DateTimeImmutable('+1 day'),
            direcao: Direcao::Entrada,
        );
    }

    protected function comando(ContextoEquipamento $contexto): ComandoAbertura
    {
        return new ComandoAbertura(
            comandoId: (string) Str::uuid7(),
            pontoAcessoId: (string) $contexto->pontoAcessoId,
            decisaoReferencia: (string) Str::uuid7(),
            chaveIdempotencia: 'contrato:'.Str::uuid7(),
            solicitadoEm: new DateTimeImmutable,
            expiraEm: new DateTimeImmutable('+15 seconds'),
            origem: 'teste',
            atorId: null,
        );
    }

    /** @return array<string, ResultadoOperacao|ResultadoColetaEventos|CapacidadesDeclaradas> */
    protected function executarTodas(ContextoEquipamento $contexto): array
    {
        $porta = $this->adaptador();
        $credencial = $this->credencial();

        return [
            'testar_conexao' => $porta->testarConexao($contexto),
            'consultar_capacidades' => $porta->consultarCapacidades($contexto),
            'sincronizar_credencial' => $porta->sincronizarCredencial($contexto, $credencial),
            'consultar_sincronizacao' => $porta->consultarSincronizacao($contexto, $credencial->credencialId, null),
            'revogar_credencial' => $porta->revogarCredencial($contexto, $credencial->credencialId, null),
            'coletar_eventos' => $porta->coletarEventos($contexto, null),
            'abertura_remota' => $porta->solicitarAbertura($contexto, $this->comando($contexto)),
            'consultar_resultado_comando' => $porta->consultarResultadoComando($contexto, (string) Str::uuid7()),
        ];
    }

    public function test_identifica_codigo_e_versao_do_contrato(): void
    {
        $this->assertNotSame('', $this->adaptador()->codigo());
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $this->adaptador()->versaoContrato());
    }

    public function test_declara_capacidades_versionadas(): void
    {
        $capacidades = $this->adaptador()->consultarCapacidades($this->contexto());

        $this->assertInstanceOf(CapacidadesDeclaradas::class, $capacidades);
        $this->assertSame($this->adaptador()->versaoContrato(), $capacidades->versaoContrato);
        foreach (Capacidade::cases() as $capacidade) {
            if (! $capacidades->suporta($capacidade)) {
                $this->assertArrayHasKey($capacidade->value, $capacidades->motivosAusencia, "Capacidade ausente sem motivo: {$capacidade->value}");
            }
        }
    }

    public function test_operacao_so_e_executada_quando_a_capacidade_foi_declarada(): void
    {
        $contexto = $this->contexto();
        $capacidades = $this->adaptador()->consultarCapacidades($contexto);
        $mapa = [
            'sincronizar_credencial' => Capacidade::SincronizarCredencial,
            'consultar_sincronizacao' => Capacidade::ConsultarSincronizacao,
            'revogar_credencial' => Capacidade::RevogarCredencial,
            'coletar_eventos' => Capacidade::ColetarEventos,
            'abertura_remota' => Capacidade::AberturaRemota,
            'consultar_resultado_comando' => Capacidade::ConsultarResultadoComando,
        ];

        foreach ($this->executarTodas($contexto) as $operacao => $resultado) {
            if (! isset($mapa[$operacao])) {
                continue;
            }

            if ($capacidades->suporta($mapa[$operacao])) {
                $this->assertNotSame(ResultadoEquipamento::CapacidadeAusente, $resultado->resultado, "{$operacao} declarada mas não executada");
            } else {
                $this->assertSame(ResultadoEquipamento::CapacidadeAusente, $resultado->resultado, "{$operacao} simulou suporte inexistente");
            }
        }
    }

    public function test_nenhuma_resposta_contem_o_segredo_tecnico(): void
    {
        $serializado = serialize(array_map(
            fn ($r) => get_object_vars($r),
            $this->executarTodas($this->contexto()),
        ));

        $this->assertStringNotContainsString(self::SEGREDO, $serializado);
    }

    public function test_dados_devolvidos_sao_somente_escalares(): void
    {
        foreach ($this->executarTodas($this->contexto()) as $operacao => $resultado) {
            if ($resultado instanceof ResultadoOperacao) {
                foreach ($resultado->dados as $valor) {
                    $this->assertTrue(is_scalar($valor) || $valor === null, "{$operacao} devolveu dado não escalar");
                }
                $this->assertTrue($resultado->idExterno === null || is_string($resultado->idExterno));
            }
        }
    }

    public function test_segredo_tecnico_nao_se_converte_em_texto_nem_serializa(): void
    {
        $segredo = new SegredoTecnico(self::SEGREDO);

        $this->assertStringNotContainsString(self::SEGREDO, (string) $segredo);
        $this->assertStringNotContainsString(self::SEGREDO, print_r($segredo, true));
        $this->expectException(\LogicException::class);
        serialize($segredo);
    }
}
