<?php

namespace App\Integracoes\Adaptadores\Hikvision;

use App\Integracoes\Dominio\Contratos\PortaEquipamentoAcesso;
use App\Integracoes\Dominio\Dados\CapacidadesDeclaradas;
use App\Integracoes\Dominio\Dados\ComandoAbertura;
use App\Integracoes\Dominio\Dados\ContextoEquipamento;
use App\Integracoes\Dominio\Dados\CredencialParaSincronizar;
use App\Integracoes\Dominio\Dados\ImagemCapturada;
use App\Integracoes\Dominio\Dados\ResultadoColetaEventos;
use App\Integracoes\Dominio\Dados\ResultadoOperacao;
use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\EstadoHomologacao;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;
use DateTimeImmutable;
use SimpleXMLElement;

/**
 * Adaptador do terminal Hikvision DS-K1T673DX-BR (ADR-016), atrás da porta
 * de equipamentos (ADR-007). Único lugar do sistema onde ISAPI aparece.
 *
 * Endpoints usados, todos validados contra o terminal real (docs/016):
 * - GET  /ISAPI/System/deviceInfo                       informações e firmware
 * - GET  /ISAPI/AccessControl/capabilities              o que o terminal declara
 * - GET  /ISAPI/Streaming/channels                      descoberta do canal de vídeo
 * - GET  /ISAPI/Streaming/channels/{canal}/picture      imagem estática (JPEG)
 * - PUT  /ISAPI/AccessControl/RemoteControl/door/{porta} comando `open` do relé
 *
 * Matriz de homologação: cada capacidade tem um estado por firmware
 * (`integracoes.hikvision.perfis_homologados`). Só executa o que está
 * implementado aqui E marcado como homologado ou em homologação para o
 * firmware informado pelo terminal. O restante é classificado como não
 * implementado, não suportado ou detectado, conforme o próprio terminal
 * declara em AccessControl/capabilities.
 *
 * HTTP 200 nunca é tratado isoladamente como sucesso: o comando de abertura
 * só vira `aceito` quando o terminal devolve ResponseStatus com statusCode 1,
 * e mesmo assim não é prova de abertura física (RN-080).
 */
class HikvisionIsapiAdaptador implements PortaEquipamentoAcesso
{
    public const CODIGO = 'hikvision-isapi';

    public const VERSAO_CONTRATO = '1.1.0';

    public const CAMINHO_INFO = '/ISAPI/System/deviceInfo';

    public const CAMINHO_CAPACIDADES = '/ISAPI/AccessControl/capabilities';

    public const CAMINHO_CANAIS = '/ISAPI/Streaming/channels';

    public const CAMINHO_IMAGEM = '/ISAPI/Streaming/channels/%d/picture';

    public const CAMINHO_PORTA = '/ISAPI/AccessControl/RemoteControl/door/%d';

    /** @var list<Capacidade> capacidades que ESTE código sabe executar */
    private const IMPLEMENTADAS = [
        Capacidade::TestarConexao,
        Capacidade::ConsultarInformacoes,
        Capacidade::ConsultarCapacidades,
        Capacidade::CapturarImagem,
        Capacidade::AberturaRemota,
    ];

    /** Indicador em AccessControl/capabilities que o terminal usa para cada capacidade. */
    private const INDICADORES = [
        'abertura_remota' => 'isSupportRemoteControlDoor',
        'coletar_eventos' => 'isSupportAcsEvent',
        'receber_eventos' => 'isSupportAcsEvent',
        'gerenciar_pessoas' => 'isSupportUserInfo',
        'sincronizar_credencial' => 'isSupportUserInfo',
        'consultar_sincronizacao' => 'isSupportUserInfo',
        'revogar_credencial' => 'isSupportUserInfoDetailDelete',
        'credencial_facial' => 'isSupportFDLib',
    ];

    private const TIPOS_IMAGEM = ['image/jpeg' => "\xFF\xD8\xFF", 'image/png' => "\x89PNG"];

    public function __construct(private ClienteIsapi $cliente) {}

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
        try {
            $info = $this->informacoes($equipamento);
        } catch (FalhaIsapi $e) {
            return $e->comoResultado();
        }

        return ResultadoOperacao::confirmado(dados: $info);
    }

    public function consultarCapacidades(ContextoEquipamento $equipamento): CapacidadesDeclaradas
    {
        try {
            $info = $this->informacoes($equipamento);
            $declarado = $this->declaradoPeloTerminal($equipamento);
            $canal = $this->canalDeVideo($equipamento);
        } catch (FalhaIsapi $e) {
            return new CapacidadesDeclaradas(
                $e->resultado,
                self::VERSAO_CONTRATO,
                $equipamento->firmwareVersao,
                [],
                array_fill_keys(array_map(fn (Capacidade $c) => $c->value, Capacidade::cases()), "terminal não consultado: {$e->getMessage()}"),
                $e->getMessage(),
            );
        }

        $firmware = $info['firmware'];
        $perfil = $this->perfil($firmware);
        $evidencia = [
            ...$declarado,
            'testar_conexao' => true,
            'consultar_informacoes' => true,
            'consultar_capacidades' => true,
            'capturar_imagem' => $canal !== null,
        ];

        $estados = [];
        $motivos = [];
        foreach (Capacidade::cases() as $capacidade) {
            [$estado, $motivo] = $this->classificar($capacidade, $perfil, $evidencia[$capacidade->value] ?? null, $firmware);
            $estados[$capacidade->value] = $estado;
            if ($motivo !== null) {
                $motivos[$capacidade->value] = $motivo;
            }
        }

        return new CapacidadesDeclaradas(
            resultado: ResultadoEquipamento::Confirmado,
            versaoContrato: self::VERSAO_CONTRATO,
            firmwareVersao: $firmware,
            suportadas: array_values(array_filter(Capacidade::cases(), fn (Capacidade $c) => $estados[$c->value]->executavel())),
            motivosAusencia: $motivos,
            mensagem: "Consultado no terminal {$info['modelo']} (firmware {$firmware}).",
            consultadoNoEquipamento: true,
            estados: $estados,
        );
    }

    public function capturarImagem(ContextoEquipamento $equipamento): ImagemCapturada
    {
        if (($motivo = $this->ausencia(Capacidade::CapturarImagem, $equipamento)) !== null) {
            return ImagemCapturada::capacidadeAusente($motivo);
        }

        try {
            $canal = $this->canalDeVideo($equipamento)
                ?? throw new FalhaIsapi(ResultadoEquipamento::FalhaTecnica, 'imagem_indisponivel', 'O terminal não informou canal de vídeo ativo.');
            $resposta = $this->cliente->get($equipamento, sprintf(self::CAMINHO_IMAGEM, $canal));
        } catch (FalhaIsapi $e) {
            return ImagemCapturada::falha($e->resultado, $e->codigo, $e->getMessage());
        }

        $conteudo = $resposta->body();
        $tipo = strtolower(trim(explode(';', (string) $resposta->header('Content-Type'))[0]));
        $assinatura = self::TIPOS_IMAGEM[$tipo] ?? null;

        return match (true) {
            $conteudo === '' => ImagemCapturada::falha(ResultadoEquipamento::FalhaTecnica, 'imagem_indisponivel', 'O terminal devolveu uma imagem vazia.'),
            strlen($conteudo) > (int) config('integracoes.captura.tamanho_maximo_bytes') => ImagemCapturada::falha(ResultadoEquipamento::FalhaTecnica, 'imagem_grande_demais', 'A imagem excede o tamanho máximo aceito.'),
            $assinatura === null || ! str_starts_with($conteudo, $assinatura) => ImagemCapturada::falha(ResultadoEquipamento::FalhaTecnica, 'resposta_invalida', 'O terminal não devolveu uma imagem reconhecida.'),
            default => ImagemCapturada::capturada($conteudo, $tipo, new DateTimeImmutable),
        };
    }

    public function solicitarAbertura(ContextoEquipamento $equipamento, ComandoAbertura $comando): ResultadoOperacao
    {
        if (($motivo = $this->ausencia(Capacidade::AberturaRemota, $equipamento)) !== null) {
            return ResultadoOperacao::capacidadeAusente(Capacidade::AberturaRemota, $motivo);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<RemoteControlDoor version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema"><cmd>open</cmd></RemoteControlDoor>';

        try {
            $resposta = $this->cliente->put($equipamento, sprintf(self::CAMINHO_PORTA, $this->porta()), $xml);
            $status = $this->cliente->xml($resposta);
        } catch (FalhaIsapi $e) {
            // Sem resposta legível não se sabe se o relé foi acionado, exceto
            // quando o pedido comprovadamente não chegou ao terminal.
            return match (true) {
                $e->naoAlcancou(), $e->resultado === ResultadoEquipamento::Recusado => $e->comoResultado(),
                default => ResultadoOperacao::desconhecido($e->codigo, $e->getMessage()),
            };
        }

        $codigo = trim((string) ($status->statusCode ?? ''));
        $sub = $this->texto((string) ($status->subStatusCode ?? ''));

        return match ($codigo) {
            '1' => new ResultadoOperacao(
                ResultadoEquipamento::Aceito,
                'comando_aceito',
                'O terminal aceitou o comando de abertura. A abertura física não é comprovada por esta resposta.',
                dados: ['status_terminal' => $sub ?? 'ok', 'porta' => $this->porta()],
            ),
            '' => ResultadoOperacao::desconhecido('resposta_invalida', 'O terminal respondeu sem o status do comando; não é possível afirmar se o relé foi acionado.'),
            default => new ResultadoOperacao(
                ResultadoEquipamento::Recusado,
                'comando_recusado',
                'O terminal recusou o comando de abertura.',
                dados: ['status_terminal' => $sub, 'codigo_terminal' => $codigo],
            ),
        };
    }

    public function sincronizarCredencial(ContextoEquipamento $equipamento, CredencialParaSincronizar $credencial): ResultadoOperacao
    {
        return ResultadoOperacao::capacidadeAusente(Capacidade::SincronizarCredencial, 'fora do escopo da primeira integração');
    }

    public function revogarCredencial(ContextoEquipamento $equipamento, string $credencialId, ?string $idExterno): ResultadoOperacao
    {
        return ResultadoOperacao::capacidadeAusente(Capacidade::RevogarCredencial, 'fora do escopo da primeira integração');
    }

    public function consultarSincronizacao(ContextoEquipamento $equipamento, string $credencialId, ?string $idExterno): ResultadoOperacao
    {
        return ResultadoOperacao::capacidadeAusente(Capacidade::ConsultarSincronizacao, 'fora do escopo da primeira integração');
    }

    public function coletarEventos(ContextoEquipamento $equipamento, ?string $cursor): ResultadoColetaEventos
    {
        return new ResultadoColetaEventos(
            ResultadoEquipamento::CapacidadeAusente,
            mensagem: Capacidade::ColetarEventos->value.': fora do escopo da primeira integração',
        );
    }

    public function consultarResultadoComando(ContextoEquipamento $equipamento, string $comandoId): ResultadoOperacao
    {
        return ResultadoOperacao::capacidadeAusente(Capacidade::ConsultarResultadoComando, 'o terminal não oferece prova de execução do comando validada');
    }

    // ---- Internos ---------------------------------------------------------

    /**
     * @return array{modelo: string, firmware: string, numero_serie: ?string, nome_dispositivo: ?string, tipo_dispositivo: ?string}
     */
    private function informacoes(ContextoEquipamento $equipamento): array
    {
        $xml = $this->cliente->xml($this->cliente->get($equipamento, self::CAMINHO_INFO));

        if ($xml->getName() !== 'DeviceInfo' || trim((string) $xml->model) === '' || trim((string) $xml->firmwareVersion) === '') {
            throw new FalhaIsapi(ResultadoEquipamento::FalhaTecnica, 'resposta_invalida', 'O terminal respondeu sem modelo ou firmware.');
        }

        $versao = $this->texto((string) $xml->firmwareVersion);
        $build = $this->texto((string) $xml->firmwareReleasedDate);

        return [
            'modelo' => (string) $this->texto((string) $xml->model),
            'firmware' => trim($versao.' '.($build ?? '')),
            'numero_serie' => $this->texto((string) $xml->serialNumber),
            'nome_dispositivo' => $this->texto((string) $xml->deviceName),
            'tipo_dispositivo' => $this->texto((string) $xml->deviceType),
        ];
    }

    /**
     * O que o terminal declara em AccessControl/capabilities, por capacidade.
     * Ausência do indicador é "não sei" (null), nunca "sim".
     *
     * @return array<string, bool|null>
     */
    private function declaradoPeloTerminal(ContextoEquipamento $equipamento): array
    {
        try {
            $xml = $this->cliente->xml($this->cliente->get($equipamento, self::CAMINHO_CAPACIDADES));
        } catch (FalhaIsapi $e) {
            if ($e->codigo === 'nao_suportado') {
                return [];
            }
            throw $e;
        }

        $declarado = [];
        foreach (self::INDICADORES as $capacidade => $indicador) {
            $valor = isset($xml->{$indicador}) ? strtolower(trim((string) $xml->{$indicador})) : null;
            $declarado[$capacidade] = $valor === null ? null : $valor === 'true';
        }

        return $declarado;
    }

    /** Primeiro canal de streaming habilitado com vídeo ativo (o DS-K1T673DX-BR usa 101). */
    private function canalDeVideo(ContextoEquipamento $equipamento): ?int
    {
        $fixo = config('integracoes.hikvision.canal_imagem');
        if ($fixo !== null) {
            return (int) $fixo;
        }

        try {
            $lista = $this->cliente->xml($this->cliente->get($equipamento, self::CAMINHO_CANAIS));
        } catch (FalhaIsapi $e) {
            if ($e->codigo === 'nao_suportado') {
                return null;
            }
            throw $e;
        }

        foreach ($lista->StreamingChannel ?? [] as $canal) {
            /** @var SimpleXMLElement $canal */
            $habilitado = strtolower((string) $canal->enabled) !== 'false' && strtolower((string) $canal->Video->enabled) !== 'false';
            if ($habilitado && ctype_digit(trim((string) $canal->id))) {
                return (int) $canal->id;
            }
        }

        return null;
    }

    /**
     * @param  array<string, EstadoHomologacao>|null  $perfil
     * @return array{0: EstadoHomologacao, 1: ?string}
     */
    private function classificar(Capacidade $capacidade, ?array $perfil, ?bool $terminalDeclara, string $firmware): array
    {
        $implementada = in_array($capacidade, self::IMPLEMENTADAS, true);
        $registrado = $perfil[$capacidade->value] ?? null;

        if ($registrado === EstadoHomologacao::Bloqueada) {
            return [$registrado, 'bloqueada por decisão de projeto'];
        }
        if ($terminalDeclara === false) {
            return [EstadoHomologacao::NaoSuportada, 'o terminal informa que não oferece esta capacidade'];
        }
        if ($implementada && $registrado !== null && $registrado->executavel()) {
            return [$registrado, null];
        }
        if (! $implementada) {
            return $terminalDeclara === true
                ? [EstadoHomologacao::Detectada, 'o terminal oferece, mas o adaptador ainda não implementa']
                : [EstadoHomologacao::NaoImplementada, 'não implementada no adaptador nesta entrega'];
        }

        return [
            EstadoHomologacao::Detectada,
            $perfil === null ? "firmware {$firmware} sem homologação registrada" : 'ainda não validada em bancada para este firmware',
        ];
    }

    private function ausencia(Capacidade $capacidade, ContextoEquipamento $equipamento): ?string
    {
        if ($equipamento->firmwareVersao === null) {
            return 'firmware não inventariado';
        }

        $perfil = $this->perfil($equipamento->firmwareVersao);
        $estado = $perfil[$capacidade->value] ?? null;

        return match (true) {
            $perfil === null => "firmware {$equipamento->firmwareVersao} sem homologação registrada",
            $estado === null || ! $estado->executavel() => 'capacidade não homologada para este firmware',
            default => null,
        };
    }

    /** @return array<string, EstadoHomologacao>|null */
    private function perfil(?string $firmware): ?array
    {
        $perfis = (array) config('integracoes.hikvision.perfis_homologados');
        if ($firmware === null || ! isset($perfis[$firmware])) {
            return null;
        }

        $perfil = [];
        foreach ((array) $perfis[$firmware] as $capacidade => $estado) {
            $estado = $estado instanceof EstadoHomologacao ? $estado : EstadoHomologacao::tryFrom((string) $estado);
            if ($estado !== null) {
                $perfil[(string) $capacidade] = $estado;
            }
        }

        return $perfil;
    }

    private function porta(): int
    {
        return (int) config('integracoes.hikvision.porta_rele', 1);
    }

    private function texto(string $valor): ?string
    {
        $valor = trim(preg_replace('/[^\P{C}]/u', '', $valor) ?? '');

        return $valor === '' ? null : mb_substr($valor, 0, 120);
    }
}
