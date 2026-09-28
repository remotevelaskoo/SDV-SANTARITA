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
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;
use DateTimeImmutable;

/**
 * Adaptador do terminal Hikvision DS-K1T673DX-BR (ADR-016), atrás da porta
 * de equipamentos (ADR-007). Único lugar do sistema onde ISAPI aparece.
 *
 * Implementa somente o que foi validado em bancada contra o terminal real
 * (docs/016): informações do dispositivo, captura de imagem estática e
 * comando remoto de abertura da porta 1. Sincronização de credenciais,
 * eventos e consulta de resultado de comando continuam ausentes.
 *
 * Uma capacidade só é declarada quando está em IMPLEMENTADAS e também no
 * perfil do firmware em `integracoes.hikvision.perfis_homologados`. O
 * firmware usado é o informado pelo próprio terminal em deviceInfo.
 *
 * HTTP 200 nunca é tratado isoladamente como sucesso: o comando de abertura
 * só vira `aceito` quando o terminal devolve ResponseStatus com statusCode 1,
 * e mesmo assim não é prova de abertura física (RN-080).
 */
class HikvisionIsapiAdaptador implements PortaEquipamentoAcesso
{
    public const CODIGO = 'hikvision-isapi';

    public const VERSAO_CONTRATO = '1.0.0';

    public const CAMINHO_INFO = '/ISAPI/System/deviceInfo';

    public const CAMINHO_IMAGEM = '/ISAPI/Streaming/channels/%d/picture';

    public const CAMINHO_PORTA = '/ISAPI/AccessControl/RemoteControl/door/%d';

    /** @var list<Capacidade> */
    private const IMPLEMENTADAS = [
        Capacidade::TestarConexao,
        Capacidade::ConsultarCapacidades,
        Capacidade::CapturarImagem,
        Capacidade::AberturaRemota,
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
        } catch (FalhaIsapi $e) {
            return new CapacidadesDeclaradas(
                $e->resultado,
                self::VERSAO_CONTRATO,
                $equipamento->firmwareVersao,
                [],
                $this->motivos([], "terminal não consultado: {$e->getMessage()}"),
                $e->getMessage(),
            );
        }

        $firmware = $info['firmware'];
        $perfil = $this->perfil($firmware);
        $suportadas = array_values(array_filter(
            self::IMPLEMENTADAS,
            fn (Capacidade $c) => in_array($c->value, $perfil ?? [], true),
        ));

        return new CapacidadesDeclaradas(
            resultado: ResultadoEquipamento::Confirmado,
            versaoContrato: self::VERSAO_CONTRATO,
            firmwareVersao: $firmware,
            suportadas: $suportadas,
            motivosAusencia: $this->motivos($suportadas, $perfil === null
                ? "firmware {$firmware} sem homologação registrada"
                : 'capacidade não homologada para este firmware ou não implementada no adaptador'),
            mensagem: "Consultado no terminal {$info['modelo']} (firmware {$firmware}).",
            consultadoNoEquipamento: true,
        );
    }

    public function capturarImagem(ContextoEquipamento $equipamento): ImagemCapturada
    {
        if (($motivo = $this->ausencia(Capacidade::CapturarImagem, $equipamento)) !== null) {
            return ImagemCapturada::capacidadeAusente($motivo);
        }

        try {
            $resposta = $this->cliente->get($equipamento, sprintf(self::CAMINHO_IMAGEM, $this->canal()));
        } catch (FalhaIsapi $e) {
            return ImagemCapturada::falha($e->resultado, $e->codigo, $e->getMessage());
        }

        $conteudo = $resposta->body();
        $tipo = strtolower(trim(explode(';', (string) $resposta->header('Content-Type'))[0]));
        $assinatura = self::TIPOS_IMAGEM[$tipo] ?? null;

        if ($assinatura === null || ! str_starts_with($conteudo, $assinatura)) {
            return ImagemCapturada::falha(ResultadoEquipamento::FalhaTecnica, 'resposta_invalida', 'O terminal não devolveu uma imagem reconhecida.');
        }

        return ImagemCapturada::capturada($conteudo, $tipo, new DateTimeImmutable);
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
                dados: ['status_terminal' => $sub ?? 'ok'],
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

    private function ausencia(Capacidade $capacidade, ContextoEquipamento $equipamento): ?string
    {
        if ($equipamento->firmwareVersao === null) {
            return 'firmware não inventariado';
        }

        $perfil = $this->perfil($equipamento->firmwareVersao);

        return match (true) {
            $perfil === null => "firmware {$equipamento->firmwareVersao} sem homologação registrada",
            ! in_array($capacidade->value, $perfil, true) => 'capacidade não homologada para este firmware',
            default => null,
        };
    }

    /**
     * @param  list<Capacidade>  $suportadas
     * @return array<string, string>
     */
    private function motivos(array $suportadas, string $motivo): array
    {
        $motivos = [];
        foreach (Capacidade::cases() as $capacidade) {
            if (! in_array($capacidade, $suportadas, true)) {
                $motivos[$capacidade->value] = in_array($capacidade, self::IMPLEMENTADAS, true)
                    ? $motivo
                    : 'não implementada no adaptador nesta entrega';
            }
        }

        return $motivos;
    }

    /** @return list<string>|null */
    private function perfil(?string $firmware): ?array
    {
        $perfis = (array) config('integracoes.hikvision.perfis_homologados');

        return $firmware !== null && isset($perfis[$firmware]) ? array_values((array) $perfis[$firmware]) : null;
    }

    private function canal(): int
    {
        return (int) config('integracoes.hikvision.canal_imagem', 1);
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
