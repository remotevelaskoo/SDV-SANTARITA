<?php

namespace App\Integracoes\Infra;

use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;

/**
 * Lê o certificado HTTPS apresentado por um equipamento da rede local, para
 * o administrador conferir e decidir se confia nele (ADR-016 §8).
 *
 * A leitura não envia credencial nem requisição HTTP: só abre a negociação
 * TLS, captura o certificado e fecha. Devolve o hash SHA-256 da chave
 * pública (formato do pin do curl) e a impressão SHA-256 do certificado.
 */
class LeitorCertificadoTls
{
    /**
     * @return array{pin_sha256: string, impressao_sha256: string, sujeito: string, emissor: string, valido_ate: string, autoassinado: bool}
     */
    public function ler(string $ip, int $porta, int $timeoutSegundos = 5): array
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw new RegraIntegracaoViolada('endereco_invalido', 'Endereço de rede do terminal inválido.');
        }

        $contexto = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            // Somente para LER o certificado e mostrar ao administrador;
            // nenhuma requisição é feita por esta conexão.
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => false,
        ]]);

        $host = str_contains($ip, ':') ? "[{$ip}]" : $ip;
        $conexao = @stream_socket_client("ssl://{$host}:{$porta}", $erro, $mensagem, $timeoutSegundos, STREAM_CLIENT_CONNECT, $contexto);
        if ($conexao === false) {
            throw new RegraIntegracaoViolada('certificado_indisponivel', 'Não foi possível ler o certificado do terminal. Confira IP, porta e HTTPS.');
        }

        $certificado = stream_context_get_params($conexao)['options']['ssl']['peer_certificate'] ?? null;
        fclose($conexao);

        $chave = $certificado ? openssl_pkey_get_public($certificado) : false;
        $detalhes = $chave ? openssl_pkey_get_details($chave) : false;
        $info = $certificado ? openssl_x509_parse($certificado) : false;
        if ($detalhes === false || $info === false || ! openssl_x509_export($certificado, $pem)) {
            throw new RegraIntegracaoViolada('certificado_indisponivel', 'O terminal não apresentou um certificado legível.');
        }

        $der = base64_decode((string) preg_replace('/-----[^-]+-----|\s+/', '', $detalhes['key']), true);
        $sujeito = $this->nome($info['subject'] ?? []);
        $emissor = $this->nome($info['issuer'] ?? []);

        return [
            'pin_sha256' => base64_encode(hash('sha256', (string) $der, true)),
            'impressao_sha256' => strtoupper(implode(':', str_split((string) openssl_x509_fingerprint($certificado, 'sha256'), 2))),
            'sujeito' => $sujeito,
            'emissor' => $emissor,
            'valido_ate' => date(DATE_ATOM, (int) ($info['validTo_time_t'] ?? 0)),
            'autoassinado' => $sujeito === $emissor,
        ];
    }

    /** @param  array<string, string|list<string>>  $partes */
    private function nome(array $partes): string
    {
        return mb_substr(implode(', ', array_map(
            fn (string $chave, $valor) => $chave.'='.(is_array($valor) ? implode('/', $valor) : $valor),
            array_keys($partes),
            $partes,
        )), 0, 200);
    }
}
