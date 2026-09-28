<?php

namespace App\Integracoes\Adaptadores\Hikvision;

use App\Integracoes\Dominio\Dados\ContextoEquipamento;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;
use Throwable;

/**
 * Cliente HTTP do protocolo ISAPI, confinado ao adaptador.
 *
 * - autenticação Digest feita pelo backend; a senha só existe em memória
 *   durante a chamada e nunca vai para URL, log ou mensagem;
 * - timeout de conexão e de resposta vindos do cadastro do equipamento;
 * - sem redirecionamento;
 * - TLS: com certificado confiado pelo operador (pin da chave pública), a
 *   cadeia de CA deixa de ser exigida SOMENTE nesta chamada e o curl aborta
 *   se a chave apresentada for diferente. Sem pin, vale a validação normal.
 *   Nada disso altera a validação TLS do restante da aplicação.
 */
class ClienteIsapi
{
    /** Erros do curl que indicam problema de certificado ou negociação TLS. */
    private const ERROS_TLS = [35, 51, 53, 54, 58, 59, 60, 64, 66, 77, 80, 82, 83, 90, 91];

    /** Erros do curl em que o pedido não saiu da máquina ou não achou o terminal. */
    private const ERROS_REDE = [5, 6, 7, 45];

    public function get(ContextoEquipamento $equipamento, string $caminho): Response
    {
        return $this->enviar($equipamento, fn (PendingRequest $r) => $r->get($caminho));
    }

    public function put(ContextoEquipamento $equipamento, string $caminho, string $xml): Response
    {
        return $this->enviar($equipamento, fn (PendingRequest $r) => $r
            ->withBody($xml, 'application/xml')
            ->put($caminho));
    }

    /** Lê o corpo XML de uma resposta 2xx; resposta fora do formato é `resposta_invalida`. */
    public function xml(Response $resposta): SimpleXMLElement
    {
        $anterior = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($resposta->body(), SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }

        if ($xml === false) {
            throw new FalhaIsapi(ResultadoEquipamento::FalhaTecnica, 'resposta_invalida', 'O terminal respondeu em formato não reconhecido.', $resposta->status());
        }

        return $xml;
    }

    /** @param  callable(PendingRequest): Response  $chamada */
    private function enviar(ContextoEquipamento $equipamento, callable $chamada): Response
    {
        $requisicao = $this->requisicao($equipamento);

        try {
            $resposta = $chamada($requisicao);
        } catch (ConnectionException $e) {
            throw $this->classificarConexao($e);
        } catch (FalhaIsapi $e) {
            throw $e;
        } catch (Throwable) {
            throw new FalhaIsapi(ResultadoEquipamento::FalhaTecnica, 'falha_cliente', 'Falha inesperada no cliente de comunicação com o terminal.');
        }

        return $this->validarStatus($resposta);
    }

    private function requisicao(ContextoEquipamento $equipamento): PendingRequest
    {
        $esquema = $equipamento->esquema === 'http' ? 'http' : 'https';
        if (filter_var($equipamento->enderecoRede, FILTER_VALIDATE_IP) === false) {
            throw new FalhaIsapi(ResultadoEquipamento::Recusado, 'endereco_invalido', 'Endereço de rede do terminal inválido.');
        }

        $segredo = $equipamento->segredo();
        if ($segredo === null || $equipamento->usuarioTecnico === null || $equipamento->usuarioTecnico === '') {
            throw new FalhaIsapi(ResultadoEquipamento::Recusado, 'credencial_ausente', 'Usuário ou senha técnica do terminal não cadastrados.');
        }

        $host = str_contains($equipamento->enderecoRede, ':') ? '['.$equipamento->enderecoRede.']' : $equipamento->enderecoRede;
        $porta = $equipamento->portaRede ?? ($esquema === 'https' ? 443 : 80);
        $timeout = max(1, $equipamento->timeoutSegundos);

        $opcoes = ['allow_redirects' => false];
        if ($esquema === 'https' && $equipamento->tlsPinSha256 !== null) {
            $opcoes['verify'] = false;
            $opcoes['curl'] = [CURLOPT_PINNEDPUBLICKEY => 'sha256//'.$equipamento->tlsPinSha256];
        }

        return Http::baseUrl("{$esquema}://{$host}:{$porta}")
            ->withDigestAuth($equipamento->usuarioTecnico, $segredo->revelar())
            ->withHeaders(['X-Correlation-Id' => $equipamento->correlationId])
            ->connectTimeout(min($timeout, 5))
            ->timeout($timeout)
            ->withOptions($opcoes);
    }

    private function validarStatus(Response $resposta): Response
    {
        return match (true) {
            $resposta->successful() => $resposta,
            $resposta->status() === 401 => throw new FalhaIsapi(ResultadoEquipamento::Recusado, 'autenticacao_recusada', 'O terminal recusou o usuário ou a senha técnica.', 401),
            $resposta->status() === 403 => throw new FalhaIsapi(ResultadoEquipamento::Recusado, 'sem_permissao', 'O usuário técnico não tem permissão para esta operação no terminal.', 403),
            in_array($resposta->status(), [404, 405, 501], true) => throw new FalhaIsapi(ResultadoEquipamento::FalhaTecnica, 'nao_suportado', 'O terminal não oferece esta operação neste firmware.', $resposta->status()),
            $resposta->status() === 400 => throw new FalhaIsapi(ResultadoEquipamento::Recusado, 'requisicao_recusada', 'O terminal recusou a requisição.', 400),
            default => throw new FalhaIsapi(ResultadoEquipamento::FalhaTecnica, 'erro_terminal', 'O terminal respondeu com erro.', $resposta->status()),
        };
    }

    private function classificarConexao(ConnectionException $e): FalhaIsapi
    {
        $numero = $this->erroCurl($e);
        $texto = $e->getMessage();

        return match (true) {
            in_array($numero, self::ERROS_TLS, true) => new FalhaIsapi(
                ResultadoEquipamento::FalhaTecnica,
                'certificado_invalido',
                $numero === 90
                    ? 'O certificado apresentado pelo terminal não é o certificado confiado. Confira o equipamento antes de confiar no novo certificado.'
                    : 'O certificado do terminal não é confiável. Registre o certificado do terminal no cadastro do equipamento.',
            ),
            $numero === 28 && stripos($texto, 'connection timed out') !== false,
            $numero === 28 && stripos($texto, 'resolving timed out') !== false => new FalhaIsapi(ResultadoEquipamento::Indisponivel, 'timeout_conexao', 'O terminal não respondeu à tentativa de conexão.'),
            $numero === 28 => new FalhaIsapi(ResultadoEquipamento::ConfirmacaoDesconhecida, 'timeout', 'Tempo esgotado aguardando a resposta do terminal.'),
            in_array($numero, self::ERROS_REDE, true) => new FalhaIsapi(ResultadoEquipamento::Indisponivel, 'rede_inalcancavel', 'Terminal inalcançável na rede.'),
            default => new FalhaIsapi(ResultadoEquipamento::ConfirmacaoDesconhecida, 'conexao_interrompida', 'A comunicação com o terminal foi interrompida.'),
        };
    }

    private function erroCurl(ConnectionException $e): ?int
    {
        for ($atual = $e; $atual !== null; $atual = $atual->getPrevious()) {
            if (method_exists($atual, 'getHandlerContext')) {
                $contexto = $atual->getHandlerContext();
                if (isset($contexto['errno'])) {
                    return (int) $contexto['errno'];
                }
            }
        }

        return preg_match('/cURL error (\d+)/', $e->getMessage(), $m) ? (int) $m[1] : null;
    }
}
