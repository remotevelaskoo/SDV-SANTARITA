<?php

namespace App\Integracoes\Aplicacao;

use App\Integracoes\Dominio\Dados\FotoFacialParaEnviar;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\Arquivo;
use App\Models\PessoaArquivo;
use Illuminate\Support\Facades\Storage;

/**
 * Resolve, no worker autorizado, a foto que vai ao terminal (AJ-010).
 *
 * A outbox guarda só referências (arquivo e checksum); aqui a foto é relida
 * do armazenamento privado, conferida contra a pessoa, a implantação ativa
 * e a versão pedida, e reduzida em memória para JPEG. Nada é gravado: o
 * arquivo original continua sendo a única cópia persistida.
 */
class PreparadorFotoFacial
{
    /**
     * Formato da face que o próprio DS-K1T673DX-BR (V3.18.0) grava ao
     * cadastrar pela câmera: 352x432, em pé. Foto deitada é exibida espremida
     * na tela do terminal, então a foto é recortada no centro nesta proporção.
     */
    public const LARGURA = 352;

    public const ALTURA = 432;

    public function preparar(string $pessoaId, string $arquivoId, string $versaoFoto, string $credencialId, ?string $idExterno): FotoFacialParaEnviar
    {
        // Escopo da implantação ativa: arquivo de outra implantação não existe aqui.
        $vinculo = PessoaArquivo::query()
            ->where('pessoa_id', $pessoaId)
            ->where('arquivo_id', $arquivoId)
            ->where('category', 'foto')
            ->first();
        if ($vinculo === null) {
            throw new RegraIntegracaoViolada('foto_nao_pertence_a_pessoa', 'A foto não pertence a esta pessoa.');
        }
        if (! $vinculo->is_current) {
            throw new RegraIntegracaoViolada('foto_substituida', 'Esta foto foi substituída por outra mais recente.');
        }

        $arquivo = Arquivo::query()->find($arquivoId);
        if ($arquivo === null || ! $arquivo->isAvailable() || $arquivo->checksum_sha256 !== $versaoFoto) {
            throw new RegraIntegracaoViolada('foto_indisponivel', 'A foto pedida não está disponível nesta versão.');
        }

        $conteudo = Storage::disk($arquivo->disk)->get($arquivo->object_key);
        if (! is_string($conteudo) || hash('sha256', $conteudo) !== $versaoFoto) {
            throw new RegraIntegracaoViolada('foto_divergente', 'O arquivo da foto não confere com a versão registrada.');
        }

        return new FotoFacialParaEnviar($credencialId, $versaoFoto, $this->jpegReduzido($conteudo), 'image/jpeg', $idExterno);
    }

    private function jpegReduzido(string $conteudo): string
    {
        $imagem = function_exists('imagecreatefromstring') ? @imagecreatefromstring($conteudo) : false;
        if ($imagem === false) {
            throw new RegraIntegracaoViolada('foto_invalida', 'A foto salva não pôde ser lida como imagem.');
        }

        $largura = imagesx($imagem);
        $altura = imagesy($imagem);

        // Recorte central na proporção do terminal, sem distorcer o rosto.
        $proporcao = self::LARGURA / self::ALTURA;
        $recorteLargura = min($largura, (int) round($altura * $proporcao));
        $recorteAltura = min($altura, (int) round($largura / $proporcao));
        $origemX = intdiv($largura - $recorteLargura, 2);
        $origemY = intdiv($altura - $recorteAltura, 2);

        // Reduz até 352x432; foto menor não é ampliada.
        $escala = min(1, self::LARGURA / $recorteLargura);
        $destino = imagecreatetruecolor(max(1, (int) round($recorteLargura * $escala)), max(1, (int) round($recorteAltura * $escala)));
        imagecopyresampled($destino, $imagem, 0, 0, $origemX, $origemY, imagesx($destino), imagesy($destino), $recorteLargura, $recorteAltura);

        ob_start();
        imagejpeg($destino, null, 85);
        $jpeg = (string) ob_get_clean();
        unset($imagem, $destino);

        return $jpeg;
    }
}
