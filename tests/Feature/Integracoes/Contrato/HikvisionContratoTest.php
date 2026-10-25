<?php

namespace Tests\Feature\Integracoes\Contrato;

use App\Integracoes\Adaptadores\Hikvision\HikvisionIsapiAdaptador;
use App\Integracoes\Dominio\Contratos\PortaEquipamentoAcesso;
use App\Integracoes\Dominio\Dados\ContextoEquipamento;
use App\Integracoes\Dominio\Dados\SegredoTecnico;
use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\Direcao;
use App\Integracoes\Dominio\Enums\EstadoHomologacao;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Feature\Integracoes\Concerns\RespostasIsapi;

/**
 * Contrato do adaptador ISAPI com respostas gravadas no formato do terminal
 * (Http::fake, sem rede). Firmware homologado declara somente o que o
 * adaptador implementa; qualquer outro firmware não declara nada.
 */
class HikvisionContratoTest extends ContratoPortaEquipamentoTestCase
{
    use RespostasIsapi;

    private const FIRMWARE = 'V-CONTRATO-1';

    protected function setUp(): void
    {
        parent::setUp();
        config(['integracoes.hikvision.perfis_homologados' => [
            self::FIRMWARE => [
                'testar_conexao' => 'homologada',
                'consultar_informacoes' => 'homologada',
                'consultar_capacidades' => 'homologada',
                'capturar_imagem' => 'homologada',
                'abertura_remota' => 'em_homologacao',
                'sincronizar_credencial' => 'homologada',
                'consultar_sincronizacao' => 'homologada',
                'revogar_credencial' => 'homologada',
                // Registrar estado executável não habilita o que o adaptador não implementa.
                'coletar_eventos' => 'homologada',
                'credencial_facial' => 'bloqueada',
            ],
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
        $this->fingirTerminal(self::FIRMWARE, $sobrescrever);
    }

    private function deviceInfo(string $firmware): string
    {
        return $this->xmlDeviceInfo($firmware);
    }

    private function respostaStatus(int $codigo, string $sub): string
    {
        return $this->xmlStatus($codigo, $sub);
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
        $this->assertSame('SERIE-SINTETICA-01', $resultado->dados['numero_serie']);
    }

    public function test_firmware_homologado_declara_somente_o_que_o_adaptador_implementa(): void
    {
        $capacidades = $this->adaptador()->consultarCapacidades($this->contexto());

        $this->assertTrue($capacidades->consultadoNoEquipamento);
        $this->assertSame(self::FIRMWARE, $capacidades->firmwareVersao);
        $this->assertEqualsCanonicalizing(
            [Capacidade::TestarConexao, Capacidade::ConsultarInformacoes, Capacidade::ConsultarCapacidades, Capacidade::CapturarImagem, Capacidade::AberturaRemota,
                Capacidade::SincronizarCredencial, Capacidade::ConsultarSincronizacao, Capacidade::RevogarCredencial],
            $capacidades->suportadas,
        );
        $this->assertFalse($capacidades->suporta(Capacidade::ColetarEventos), 'Perfil não habilita capacidade não implementada.');
    }

    public function test_matriz_tem_estado_independente_por_capacidade(): void
    {
        $capacidades = $this->adaptador()->consultarCapacidades($this->contexto());

        $this->assertSame(EstadoHomologacao::Homologada, $capacidades->estado(Capacidade::CapturarImagem));
        $this->assertSame(EstadoHomologacao::EmHomologacao, $capacidades->estado(Capacidade::AberturaRemota));
        $this->assertSame(EstadoHomologacao::Homologada, $capacidades->estado(Capacidade::SincronizarCredencial));
        $this->assertSame(EstadoHomologacao::Detectada, $capacidades->estado(Capacidade::ValidadeNativa));
        $this->assertSame(EstadoHomologacao::Detectada, $capacidades->estado(Capacidade::GerenciarPessoas));
        $this->assertSame(EstadoHomologacao::Detectada, $capacidades->estado(Capacidade::ColetarEventos));
        $this->assertSame(EstadoHomologacao::Bloqueada, $capacidades->estado(Capacidade::CredencialFacial));
        $this->assertSame(EstadoHomologacao::NaoImplementada, $capacidades->estado(Capacidade::ConsultarResultadoComando));
    }

    public function test_terminal_que_nega_a_capacidade_vence_o_perfil(): void
    {
        $this->terminal(['*/ISAPI/AccessControl/capabilities' => Http::response($this->xmlCapacidadesAcesso(porta: false), 200)]);

        $capacidades = $this->adaptador()->consultarCapacidades($this->contexto());

        $this->assertSame(EstadoHomologacao::NaoSuportada, $capacidades->estado(Capacidade::AberturaRemota));
        $this->assertFalse($capacidades->suporta(Capacidade::AberturaRemota));
    }

    public function test_canal_de_video_e_descoberto_no_terminal(): void
    {
        $this->adaptador()->capturarImagem($this->contexto());

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/ISAPI/Streaming/channels'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/ISAPI/Streaming/channels/101/picture'));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/channels/100/'));
    }

    public function test_imagem_acima_do_limite_e_recusada(): void
    {
        config(['integracoes.captura.tamanho_maximo_bytes' => 10]);

        $this->assertSame('imagem_grande_demais', $this->adaptador()->capturarImagem($this->contexto())->codigo);
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

        $this->terminal(['*/ISAPI/Streaming/channels/101/picture' => Http::response('<html></html>', 200, ['Content-Type' => 'image/jpeg'])]);
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

    public function test_grava_pessoa_com_validade_local_sem_foto_e_id_derivado_do_uuid(): void
    {
        $credencial = $this->credencial();
        $resultado = $this->adaptador()->sincronizarCredencial($this->contexto(), $credencial);

        $this->assertSame(ResultadoEquipamento::Confirmado, $resultado->resultado);
        $this->assertSame(str_replace('-', '', $credencial->credencialId), $resultado->idExterno);
        Http::assertSent(function (Request $r) use ($credencial) {
            if (! str_contains($r->url(), '/ISAPI/AccessControl/UserInfo/SetUp')) {
                return false;
            }
            $usuario = $r->data()['UserInfo'];

            return $r->method() === 'PUT'
                && $usuario['employeeNo'] === str_replace('-', '', $credencial->credencialId)
                && $usuario['Valid'] === [
                    'enable' => true,
                    'beginTime' => $credencial->vigenciaInicio->format('Y-m-d\TH:i:s'),
                    'endTime' => $credencial->vigenciaFim->format('Y-m-d\TH:i:s'),
                    'timeType' => 'local',
                ]
                // Só identificação, validade e porta: nada de foto, template, cartão ou senha.
                && array_keys($usuario) === ['employeeNo', 'name', 'userType', 'Valid', 'doorRight', 'RightPlan'];
        });
    }

    public function test_fuso_ou_relogio_do_terminal_divergente_recusa_sem_gravar(): void
    {
        // Como na bancada: terminal em UTC+8 com a portaria em UTC-3.
        $this->terminal(['*/ISAPI/System/time' => Http::response($this->xmlHora(new \DateTimeImmutable('now', new \DateTimeZone('+08:00'))), 200)]);
        $resultado = $this->adaptador()->sincronizarCredencial($this->contexto(), $this->credencial());
        $this->assertSame(ResultadoEquipamento::Recusado, $resultado->resultado);
        $this->assertSame('fuso_terminal_divergente', $resultado->codigo);

        $this->terminal(['*/ISAPI/System/time' => Http::response($this->xmlHora(new \DateTimeImmutable('+10 minutes', new \DateTimeZone(config('app.timezone')))), 200)]);
        $resultado = $this->adaptador()->sincronizarCredencial($this->contexto(), $this->credencial());
        $this->assertSame('relogio_terminal_divergente', $resultado->codigo);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'UserInfo/SetUp'));
    }

    public function test_consulta_presenca_e_remocao_pelo_employee_no(): void
    {
        $credencial = $this->credencial();
        $employeeNo = str_replace('-', '', $credencial->credencialId);

        $ausente = $this->adaptador()->consultarSincronizacao($this->contexto(), $credencial->credencialId, null);
        $this->assertFalse($ausente->dados['presente']);

        $this->terminal(['*/ISAPI/AccessControl/UserInfo/Search*' => Http::response(['UserInfoSearch' => [
            'numOfMatches' => 1,
            'UserInfo' => [['employeeNo' => $employeeNo, 'Valid' => ['beginTime' => '2026-10-25T17:40:50', 'endTime' => '2026-10-26T17:40:50']]],
        ]], 200)]);
        $presente = $this->adaptador()->consultarSincronizacao($this->contexto(), $credencial->credencialId, $employeeNo);
        $this->assertTrue($presente->dados['presente']);
        $this->assertSame('2026-10-26T17:40:50', $presente->dados['validade_fim']);

        $removido = $this->adaptador()->revogarCredencial($this->contexto(), $credencial->credencialId, $employeeNo);
        $this->assertSame(ResultadoEquipamento::Confirmado, $removido->resultado);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'UserInfo/Delete') && $r->data()['UserInfoDelCond']['EmployeeNoList'][0]['employeeNo'] === $employeeNo);
    }

    public function test_recusa_do_terminal_e_timeout_na_gravacao(): void
    {
        $this->terminal(['*/ISAPI/AccessControl/UserInfo/SetUp*' => Http::response($this->jsonStatus(6, 'badJsonContent'), 200)]);
        $recusa = $this->adaptador()->sincronizarCredencial($this->contexto(), $this->credencial());
        $this->assertSame(ResultadoEquipamento::Recusado, $recusa->resultado);
        $this->assertSame('badJsonContent', $recusa->dados['status_terminal']);

        $this->terminal(['*/ISAPI/AccessControl/UserInfo/SetUp*' => $this->falhaDeConexao('cURL error 28: Operation timed out after 2000 milliseconds')]);
        $timeout = $this->adaptador()->sincronizarCredencial($this->contexto(), $this->credencial());
        $this->assertSame(ResultadoEquipamento::ConfirmacaoDesconhecida, $timeout->resultado, 'Timeout nunca conta como sucesso.');
    }
}
