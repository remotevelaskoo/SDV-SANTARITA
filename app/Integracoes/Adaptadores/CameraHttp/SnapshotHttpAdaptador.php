<?php

namespace App\Integracoes\Adaptadores\CameraHttp;

use App\Integracoes\Dominio\Contratos\PortaCameraSnapshot;
use App\Integracoes\Dominio\Dados\ContextoCamera;
use App\Integracoes\Dominio\Dados\ImagemCapturada;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;
use DateTimeImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Snapshot HTTP/HTTPS genérico (AJ-006): GET no caminho cadastrado para a
 * câmera ou para o gateway de vídeo que atende câmeras RTSP/ONVIF.
 *
 * - autenticação Digest ou Basic montada aqui, com a senha só em memória;
 * - sem redirecionamento e com tempo limite do cadastro;
 * - HTTPS com pin da chave pública quando o certificado é autoassinado;
 * - aceita só JPEG ou PNG até o tamanho máximo; o resto é falha técnica;
 * - nenhuma URL, senha ou conteúdo vai para mensagem ou log.
 */
class SnapshotHttpAdaptador implements PortaCameraSnapshot
{
    public function origem(): string
    {
        return 'camera';
    }

    public function capturarSnapshot(ContextoCamera $camera): ImagemCapturada
    {
        if (filter_var($camera->enderecoRede, FILTER_VALIDATE_IP) === false) {
            return ImagemCapturada::falha(ResultadoEquipamento::Recusado, 'endereco_invalido', 'Endereço de rede da câmera inválido.');
        }
        if (! preg_match('#^/[A-Za-z0-9._~/?&=%:+-]*$#', $camera->caminhoSnapshot)) {
            return ImagemCapturada::falha(ResultadoEquipamento::Recusado, 'caminho_invalido', 'Caminho de captura da câmera inválido.');
        }

        $esquema = $camera->esquema === 'http' ? 'http' : 'https';
        $host = str_contains($camera->enderecoRede, ':') ? '['.$camera->enderecoRede.']' : $camera->enderecoRede;
        $porta = $camera->portaRede ?? ($esquema === 'https' ? 443 : 80);
        $timeout = max(1, $camera->timeoutSegundos);

        $opcoes = ['allow_redirects' => false];
        if ($esquema === 'https' && $camera->tlsPinSha256 !== null) {
            $opcoes['verify'] = false;
            $opcoes['curl'] = [CURLOPT_PINNEDPUBLICKEY => 'sha256//'.$camera->tlsPinSha256];
        }

        $requisicao = Http::baseUrl("{$esquema}://{$host}:{$porta}")
            ->withHeaders(['Accept' => 'image/jpeg, image/png', 'X-Correlation-Id' => $camera->correlationId])
            ->connectTimeout(min($timeout, 5))
            ->timeout($timeout)
            ->withOptions($opcoes);

        $segredo = $camera->segredo();
        if ($camera->usuarioTecnico !== null && $camera->usuarioTecnico !== '' && $segredo !== null) {
            $requisicao = $requisicao->withDigestAuth($camera->usuarioTecnico, $segredo->revelar());
        }

        try {
            $resposta = $requisicao->get($camera->caminhoSnapshot);
        } catch (ConnectionException) {
            return ImagemCapturada::falha(ResultadoEquipamento::Indisponivel, 'camera_inalcancavel', 'A câmera não respondeu. Confira a rede ou use a placa digitada.');
        } catch (Throwable) {
            return ImagemCapturada::falha(ResultadoEquipamento::FalhaTecnica, 'falha_cliente', 'Falha inesperada ao falar com a câmera.');
        }

        if (in_array($resposta->status(), [401, 403], true)) {
            return ImagemCapturada::falha(ResultadoEquipamento::Recusado, 'autenticacao_recusada', 'A câmera recusou o usuário ou a senha técnica.');
        }
        if (! $resposta->successful()) {
            return ImagemCapturada::falha(ResultadoEquipamento::FalhaTecnica, 'erro_camera', 'A câmera respondeu com erro.');
        }

        $tipo = strtolower(trim(explode(';', (string) $resposta->header('Content-Type'))[0]));
        $conteudo = $resposta->body();

        if (! in_array($tipo, ['image/jpeg', 'image/png'], true)) {
            return ImagemCapturada::falha(ResultadoEquipamento::FalhaTecnica, 'resposta_invalida', 'A câmera não devolveu uma imagem.');
        }
        if ($conteudo === '' || strlen($conteudo) > $camera->tamanhoMaximoBytes) {
            return ImagemCapturada::falha(ResultadoEquipamento::FalhaTecnica, 'imagem_tamanho_invalido', 'A imagem da câmera está vazia ou grande demais.');
        }

        return ImagemCapturada::capturada($conteudo, $tipo, new DateTimeImmutable);
    }
}
