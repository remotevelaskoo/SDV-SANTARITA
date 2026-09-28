<?php

namespace Tests\Feature\Integracoes\Contrato;

use App\Integracoes\Adaptadores\Hikvision\HikvisionIsapiAdaptador;
use App\Integracoes\Dominio\Contratos\PortaEquipamentoAcesso;
use App\Integracoes\Dominio\Dados\ContextoEquipamento;
use App\Integracoes\Dominio\Dados\SegredoTecnico;
use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\Direcao;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Contrato do adaptador ISAPI com respostas gravadas no formato do terminal
 * (Http::fake, sem rede). Firmware homologado declara somente o que o
 * adaptador implementa; qualquer outro firmware não declara nada.
 */
class HikvisionContratoTest extends ContratoPortaEquipamentoTestCase
{
    private const FIRMWARE = 'V-CONTRATO-1';

    protected function setUp(): void
    {
        parent::setUp();
        config(['integracoes.hikvision.perfis_homologados' => [
            self::FIRMWARE => ['testar_conexao', 'consultar_capacidades', 'capturar_imagem', 'abertura_remota', 'sincronizar_credencial'],
        ]]);
        $this->terminal();
    }

    protected function adaptador(): PortaEquipamentoAcesso
    {
        return app(HikvisionIsapiAdaptador::class);
    }

    /** @param  array<string, mixed>  $sobrescrever */
    private function terminal(array $sobrescrever = []): void
    {
        // Fábrica nova a cada cenário: no Http::fake o primeiro stub registrado vence.
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(array_merge([
            '*/ISAPI/System/deviceInfo' => Http::response($this->deviceInfo(self::FIRMWARE), 200, ['Content-Type' => 'application/xml']),
            '*/ISAPI/Streaming/channels/1/picture' => Http::response("\xFF\xD8\xFF\xE0".str_repeat('x', 64), 200, ['Content-Type' => 'image/jpeg']),
            '*/ISAPI/AccessControl/RemoteControl/door/1' => Http::response($this->respostaStatus(1, 'ok'), 200, ['Content-Type' => 'application/xml']),
        ], $sobrescrever));
    }

    private function deviceInfo(string $firmware): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><DeviceInfo version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema">'
            .'<deviceName>Access Controller</deviceName><model>DS-K1T673DX-BR</model><serialNumber>SERIE-CONTRATO</serialNumber>'
            ."<firmwareVersion>{$firmware}</firmwareVersion><deviceType>ACS</deviceType></DeviceInfo>";
    }

    private function respostaStatus(int $codigo, string $sub): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><ResponseStatus version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema">'
            ."<requestURL>/ISAPI/AccessControl/RemoteControl/door/1</requestURL><statusCode>{$codigo}</statusCode>"
            ."<statusString>OK</statusString><subStatusCode>{$sub}</subStatusCode></ResponseStatus>";
    }

    private function falhaDeConexao(string $mensagem): \Closure
    {
        return fn () => throw new ConnectionException($mensagem);
    }

    public function test_informacoes_do_terminal_sao_normalizadas(): void
    {
        $resultado = $this->adaptador()->testarConexao($this->contexto());

        $this->assertSame(ResultadoEquipamento::Confirmado, $resultado->resultado);
        $this->assertSame('DS-K1T673DX-BR', $resultado->dados['modelo']);
        $this->assertSame(self::FIRMWARE, $resultado->dados['firmware']);
        $this->assertSame('SERIE-CONTRATO', $resultado->dados['numero_serie']);
    }

    public function test_firmware_homologado_declara_somente_o_que_o_adaptador_implementa(): void
    {
        $capacidades = $this->adaptador()->consultarCapacidades($this->contexto());

        $this->assertTrue($capacidades->consultadoNoEquipamento);
        $this->assertSame(self::FIRMWARE, $capacidades->firmwareVersao);
        $this->assertEqualsCanonicalizing(
            [Capacidade::TestarConexao, Capacidade::ConsultarCapacidades, Capacidade::CapturarImagem, Capacidade::AberturaRemota],
            $capacidades->suportadas,
        );
        $this->assertFalse($capacidades->suporta(Capacidade::SincronizarCredencial), 'Perfil não habilita capacidade não implementada.');
    }

    public function test_firmware_nao_homologado_nao_declara_nada_nem_executa(): void
    {
        $this->terminal(['*/ISAPI/System/deviceInfo' => Http::response($this->deviceInfo('V0.0.0'), 200)]);
        $capacidades = $this->adaptador()->consultarCapacidades($this->contexto('V0.0.0'));

        $this->assertSame([], $capacidades->suportadas);
        $this->assertStringContainsString('sem homologação registrada', $capacidades->motivosAusencia['abertura_remota']);
        $this->assertSame(ResultadoEquipamento::CapacidadeAusente, $this->adaptador()->solicitarAbertura($this->contexto('V0.0.0'), $this->comando($this->contexto()))->resultado);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'RemoteControl'));
    }

    public function test_falhas_de_conexao_sao_classificadas(): void
    {
        $casos = [
            'cURL error 7: Failed to connect to 192.168.50.10 port 443' => [ResultadoEquipamento::Indisponivel, 'rede_inalcancavel'],
            'cURL error 28: Connection timed out after 2001 milliseconds' => [ResultadoEquipamento::Indisponivel, 'timeout_conexao'],
            'cURL error 28: Operation timed out after 2000 milliseconds with 0 bytes received' => [ResultadoEquipamento::ConfirmacaoDesconhecida, 'timeout'],
            'cURL error 60: SSL certificate problem: self-signed certificate' => [ResultadoEquipamento::FalhaTecnica, 'certificado_invalido'],
            'cURL error 90: SSL: public key does not match pinned public key' => [ResultadoEquipamento::FalhaTecnica, 'certificado_invalido'],
        ];

        foreach ($casos as $mensagem => [$resultado, $codigo]) {
            $this->terminal(['*/ISAPI/System/deviceInfo' => $this->falhaDeConexao($mensagem)]);

            $teste = $this->adaptador()->testarConexao($this->contexto());

            $this->assertSame($resultado, $teste->resultado, $mensagem);
            $this->assertSame($codigo, $teste->codigo, $mensagem);
            $this->assertStringNotContainsString('192.168', (string) $teste->mensagem);
        }
    }

    public function test_autenticacao_recusada_e_resposta_incompativel(): void
    {
        $this->terminal(['*/ISAPI/System/deviceInfo' => Http::response('', 401)]);
        $this->assertSame('autenticacao_recusada', $this->adaptador()->testarConexao($this->contexto())->codigo);

        $this->terminal(['*/ISAPI/System/deviceInfo' => Http::response('<html>login</html>', 200)]);
        $this->assertSame('resposta_invalida', $this->adaptador()->testarConexao($this->contexto())->codigo);
    }

    public function test_sem_usuario_ou_senha_nada_e_enviado(): void
    {
        $contexto = new ContextoEquipamento(
            implantacaoId: (string) Str::uuid7(), equipamentoId: (string) Str::uuid7(), pontoAcessoId: null,
            fabricante: 'Hikvision', modelo: 'DS-K1T673DX-BR', firmwareVersao: self::FIRMWARE,
            enderecoRede: '192.168.50.10', portaRede: null, protocolo: 'isapi', direcao: Direcao::Entrada,
            timeoutSegundos: 2, usuarioTecnico: 'admin', obterSegredo: fn () => null, correlationId: (string) Str::uuid7(),
        );

        $this->assertSame('credencial_ausente', $this->adaptador()->testarConexao($contexto)->codigo);
        Http::assertNothingSent();
    }

    public function test_abertura_aceita_nao_e_confirmacao_fisica(): void
    {
        $resultado = $this->adaptador()->solicitarAbertura($this->contexto(), $this->comando($this->contexto()));

        $this->assertSame(ResultadoEquipamento::Aceito, $resultado->resultado);
        $this->assertStringContainsString('não é comprovada', (string) $resultado->mensagem);
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT'
            && str_ends_with($r->url(), '/ISAPI/AccessControl/RemoteControl/door/1')
            && str_contains($r->body(), '<cmd>open</cmd>')
            && ! str_contains($r->url(), self::SEGREDO));
    }

    public function test_abertura_recusada_desconhecida_e_nao_alcancada(): void
    {
        $casos = [
            [Http::response($this->respostaStatus(4, 'notSupport'), 200), ResultadoEquipamento::Recusado],
            [Http::response('', 200), ResultadoEquipamento::ConfirmacaoDesconhecida],
            [Http::response('<ResponseStatus><statusString>OK</statusString></ResponseStatus>', 200), ResultadoEquipamento::ConfirmacaoDesconhecida],
            [$this->falhaDeConexao('cURL error 28: Operation timed out after 2000 milliseconds with 0 bytes received'), ResultadoEquipamento::ConfirmacaoDesconhecida],
            [$this->falhaDeConexao('cURL error 7: Failed to connect'), ResultadoEquipamento::Indisponivel],
            [Http::response('', 500), ResultadoEquipamento::ConfirmacaoDesconhecida],
        ];

        foreach ($casos as $i => [$resposta, $esperado]) {
            $this->terminal(['*/ISAPI/AccessControl/RemoteControl/door/1' => $resposta]);

            $this->assertSame($esperado, $this->adaptador()->solicitarAbertura($this->contexto(), $this->comando($this->contexto()))->resultado, "caso {$i}");
        }
    }

    public function test_captura_valida_tipo_e_assinatura(): void
    {
        $imagem = $this->adaptador()->capturarImagem($this->contexto());
        $this->assertSame(ResultadoEquipamento::Confirmado, $imagem->resultado);
        $this->assertSame('image/jpeg', $imagem->tipoMime);
        $this->assertStringNotContainsString('xxxx', print_r($imagem, true));

        $this->terminal(['*/ISAPI/Streaming/channels/1/picture' => Http::response('<html></html>', 200, ['Content-Type' => 'image/jpeg'])]);
        $this->assertSame('resposta_invalida', $this->adaptador()->capturarImagem($this->contexto())->codigo);
    }

    public function test_certificado_confiado_e_esquema_vao_para_a_chamada(): void
    {
        $contexto = new ContextoEquipamento(
            implantacaoId: (string) Str::uuid7(), equipamentoId: (string) Str::uuid7(), pontoAcessoId: null,
            fabricante: 'Hikvision', modelo: 'DS-K1T673DX-BR', firmwareVersao: self::FIRMWARE,
            enderecoRede: '192.168.50.10', portaRede: 8443, protocolo: 'isapi', direcao: Direcao::Entrada,
            timeoutSegundos: 2, usuarioTecnico: 'admin', obterSegredo: fn () => new SegredoTecnico(self::SEGREDO),
            correlationId: (string) Str::uuid7(), esquema: 'https', tlsPinSha256: str_repeat('A', 43).'=',
        );

        $this->adaptador()->testarConexao($contexto);

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://192.168.50.10:8443/ISAPI/System/deviceInfo'));
    }
}
