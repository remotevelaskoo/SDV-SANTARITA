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
use DateTimeZone;
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
 * - GET  /ISAPI/System/time                             hora e fuso do terminal
 * - PUT  /ISAPI/AccessControl/UserInfo/SetUp            grava ou altera o usuário (com Valid)
 * - POST /ISAPI/AccessControl/UserInfo/Search           consulta pelo employeeNo
 * - PUT  /ISAPI/AccessControl/UserInfo/Delete           remove pelo employeeNo
 *
 * Os quatro últimos foram validados em bancada em 25/10/2026 (AJ-006, docs/020
 * §9) com usuário fictício, sem foto: gravar, alterar, consultar e remover,
 * com o período de validade devolvido igual ao enviado.
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

    public const CAMINHO_HORA = '/ISAPI/System/time';

    public const CAMINHO_USUARIO_GRAVAR = '/ISAPI/AccessControl/UserInfo/SetUp?format=json';

    public const CAMINHO_USUARIO_CONSULTAR = '/ISAPI/AccessControl/UserInfo/Search?format=json';

    public const CAMINHO_USUARIO_REMOVER = '/ISAPI/AccessControl/UserInfo/Delete?format=json';

    /** Maior data aceita pelo terminal em Valid (UserInfo/capabilities). */
    private const VALIDADE_MAXIMA = '2037-12-31T23:59:59';

    /** @var list<Capacidade> capacidades que ESTE código sabe executar */
    private const IMPLEMENTADAS = [
        Capacidade::TestarConexao,
        Capacidade::ConsultarInformacoes,
        Capacidade::ConsultarCapacidades,
        Capacidade::CapturarImagem,
        Capacidade::AberturaRemota,
        Capacidade::SincronizarCredencial,
        Capacidade::RevogarCredencial,
        Capacidade::ConsultarSincronizacao,
        Capacidade::ValidadeNativa,
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
        'validade_nativa' => 'isSupportUserInfo',
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
            $resposta = $this->cliente->put($equipamento, sprintf(self::CAMINHO_PORTA, $equipamento->portaRele), $xml);
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
                dados: ['status_terminal' => $sub ?? 'ok', 'porta' => $equipamento->portaRele],
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

    /**
     * Grava (ou altera) a pessoa no terminal com o período de validade. Não
     * envia foto, template, cartão nem senha: só identificação e validade.
     * Antes de gravar, confere hora e fuso do terminal: com fuso diferente o
     * terminal aplicaria o período em outra hora local.
     */
    public function sincronizarCredencial(ContextoEquipamento $equipamento, CredencialParaSincronizar $credencial): ResultadoOperacao
    {
        if (($motivo = $this->ausencia(Capacidade::SincronizarCredencial, $equipamento)) !== null) {
            return ResultadoOperacao::capacidadeAusente(Capacidade::SincronizarCredencial, $motivo);
        }
        if ($credencial->ehFacial()) {
            return new ResultadoOperacao(ResultadoEquipamento::Recusado, 'biometria_bloqueada_adr013', 'Credencial facial bloqueada até a aprovação do ADR-013.');
        }

        $employeeNo = $this->employeeNo($credencial->credencialId);

        try {
            $fuso = $this->conferirRelogio($equipamento, $credencial->vigenciaInicio);
            $resposta = $this->cliente->json($this->cliente->putJson($equipamento, self::CAMINHO_USUARIO_GRAVAR, ['UserInfo' => [
                'employeeNo' => $employeeNo,
                'name' => mb_substr($credencial->nomeExibicao, 0, 128),
                'userType' => 'normal',
                'Valid' => [
                    'enable' => true,
                    'beginTime' => $credencial->vigenciaInicio->setTimezone($fuso)->format('Y-m-d\TH:i:s'),
                    'endTime' => $credencial->vigenciaFim?->setTimezone($fuso)->format('Y-m-d\TH:i:s') ?? self::VALIDADE_MAXIMA,
                    'timeType' => 'local',
                ],
                'doorRight' => (string) $equipamento->portaRele,
                'RightPlan' => [['doorNo' => $equipamento->portaRele, 'planTemplateNo' => '1']],
            ]]));
        } catch (FalhaIsapi $e) {
            // Gravar é idempotente (SetUp): sem resposta, a reconciliação consulta antes de repetir.
            return $e->comoResultado();
        }

        return $this->statusJson($resposta, $employeeNo, 'Cadastro gravado no terminal com o período de validade.');
    }

    public function revogarCredencial(ContextoEquipamento $equipamento, string $credencialId, ?string $idExterno): ResultadoOperacao
    {
        if (($motivo = $this->ausencia(Capacidade::RevogarCredencial, $equipamento)) !== null) {
            return ResultadoOperacao::capacidadeAusente(Capacidade::RevogarCredencial, $motivo);
        }

        try {
            $resposta = $this->cliente->json($this->cliente->putJson($equipamento, self::CAMINHO_USUARIO_REMOVER, ['UserInfoDelCond' => [
                'EmployeeNoList' => [['employeeNo' => $idExterno ?? $this->employeeNo($credencialId)]],
            ]]));
        } catch (FalhaIsapi $e) {
            return $e->comoResultado();
        }

        return $this->statusJson($resposta, null, 'Cadastro removido do terminal.');
    }

    public function consultarSincronizacao(ContextoEquipamento $equipamento, string $credencialId, ?string $idExterno): ResultadoOperacao
    {
        if (($motivo = $this->ausencia(Capacidade::ConsultarSincronizacao, $equipamento)) !== null) {
            return ResultadoOperacao::capacidadeAusente(Capacidade::ConsultarSincronizacao, $motivo);
        }

        $employeeNo = $idExterno ?? $this->employeeNo($credencialId);

        try {
            $resposta = $this->cliente->json($this->cliente->postJson($equipamento, self::CAMINHO_USUARIO_CONSULTAR, ['UserInfoSearchCond' => [
                'searchID' => substr(hash('sha256', $equipamento->correlationId.$employeeNo), 0, 16),
                'searchResultPosition' => 0,
                'maxResults' => 1,
                'EmployeeNoList' => [['employeeNo' => $employeeNo]],
            ]]));
        } catch (FalhaIsapi $e) {
            return $e->comoResultado();
        }

        $busca = $resposta['UserInfoSearch'] ?? null;
        if (! is_array($busca) || ! isset($busca['numOfMatches'])) {
            return new ResultadoOperacao(ResultadoEquipamento::FalhaTecnica, 'resposta_invalida', 'O terminal respondeu à consulta fora do formato esperado.');
        }

        $presente = (int) $busca['numOfMatches'] > 0;
        $usuario = $presente ? ($busca['UserInfo'][0] ?? []) : [];

        return ResultadoOperacao::confirmado($presente ? $employeeNo : null, array_filter([
            'presente' => $presente,
            'validade_inicio' => $this->texto((string) ($usuario['Valid']['beginTime'] ?? '')),
            'validade_fim' => $this->texto((string) ($usuario['Valid']['endTime'] ?? '')),
        ], fn ($v) => $v !== null));
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
     * Identificador do usuário no terminal, derivado do UUID interno da
     * credencial (32 caracteres, limite do terminal). Fica registrado como
     * referência externa e nunca substitui o UUID do SDV.
     */
    private function employeeNo(string $credencialId): string
    {
        return substr(str_replace('-', '', strtolower($credencialId)), 0, 32);
    }

    /**
     * Hora e fuso do terminal. Fuso diferente do da vigência, ou relógio fora
     * da tolerância, recusa o envio: o terminal liberaria fora do período.
     */
    private function conferirRelogio(ContextoEquipamento $equipamento, DateTimeImmutable $referencia): DateTimeZone
    {
        $xml = $this->cliente->xml($this->cliente->get($equipamento, self::CAMINHO_HORA));
        $local = $this->texto((string) $xml->localTime);
        $horaTerminal = $local !== null ? DateTimeImmutable::createFromFormat(DATE_ATOM, $local) : false;
        if ($horaTerminal === false) {
            throw new FalhaIsapi(ResultadoEquipamento::FalhaTecnica, 'resposta_invalida', 'O terminal não informou a hora local.');
        }

        $agora = new DateTimeImmutable;
        $esperado = $referencia->getOffset();
        if ($horaTerminal->getOffset() !== $esperado) {
            throw new FalhaIsapi(ResultadoEquipamento::Recusado, 'fuso_terminal_divergente', sprintf(
                'O terminal está no fuso UTC%s e a portaria em UTC%s. Ajuste o fuso do terminal (Configuração › Hora) antes de enviar cadastros com validade.',
                $horaTerminal->format('P'),
                $referencia->format('P'),
            ));
        }

        $diferenca = abs($horaTerminal->getTimestamp() - $agora->getTimestamp());
        $tolerancia = (int) config('integracoes.tolerancia_relogio_segundos', 120);
        if ($diferenca > $tolerancia) {
            throw new FalhaIsapi(ResultadoEquipamento::Recusado, 'relogio_terminal_divergente', sprintf(
                'O relógio do terminal está %d min diferente do servidor. Acerte a hora do terminal antes de enviar cadastros com validade.',
                (int) round($diferenca / 60),
            ));
        }

        return $horaTerminal->getTimezone();
    }

    /** @param  array<string, mixed>  $resposta */
    private function statusJson(array $resposta, ?string $idExterno, string $mensagem): ResultadoOperacao
    {
        $codigo = (string) ($resposta['statusCode'] ?? '');
        $sub = $this->texto((string) ($resposta['subStatusCode'] ?? ''));

        return match ($codigo) {
            '1' => new ResultadoOperacao(ResultadoEquipamento::Confirmado, 'ok', $mensagem, $idExterno, ['status_terminal' => $sub ?? 'ok']),
            '' => new ResultadoOperacao(ResultadoEquipamento::FalhaTecnica, 'resposta_invalida', 'O terminal respondeu sem o status da operação.'),
            default => new ResultadoOperacao(ResultadoEquipamento::Recusado, 'recusado_pelo_terminal', 'O terminal recusou a operação.', dados: ['status_terminal' => $sub, 'codigo_terminal' => $codigo]),
        };
    }

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

    private function texto(string $valor): ?string
    {
        $valor = trim(preg_replace('/[^\P{C}]/u', '', $valor) ?? '');

        return $valor === '' ? null : mb_substr($valor, 0, 120);
    }
}
