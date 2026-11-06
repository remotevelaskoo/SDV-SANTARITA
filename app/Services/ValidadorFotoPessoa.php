<?php

namespace App\Services;

/**
 * Conferência mínima da foto atual da pessoa no servidor (AJ-010): formato
 * real, dimensões e iluminação. Não detecta rosto nem prova de vida: a
 * presença de um rosto não é afirmada por aqui (docs/021 §5).
 */
class ValidadorFotoPessoa
{
    public const TAMANHO_MAXIMO_KB = 5120;

    public const LADO_MINIMO = 320;

    /** Mensagem de orientação, ou null quando a foto pode ser usada. */
    public function problema(string $conteudo): ?string
    {
        $info = @getimagesizefromstring($conteudo);
        if ($conteudo === '' || $info === false || ! in_array($info['mime'], ['image/jpeg', 'image/png'], true)) {
            return 'O arquivo não é uma foto JPEG ou PNG válida. Fotografe novamente.';
        }
        if (min($info[0], $info[1]) < self::LADO_MINIMO) {
            return 'Foto pequena demais. Aproxime a pessoa da câmera ou use resolução maior.';
        }

        $brilho = $this->brilhoMedio($conteudo);
        if ($brilho !== null && $brilho < 40) {
            return 'Foto escura. Melhore a iluminação do rosto e fotografe novamente.';
        }
        if ($brilho !== null && $brilho > 235) {
            return 'Foto clara demais. Evite luz direta na câmera e fotografe novamente.';
        }

        return null;
    }

    private function brilhoMedio(string $conteudo): ?float
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $imagem = @imagecreatefromstring($conteudo);
        if ($imagem === false) {
            return null;
        }

        $amostra = imagecreatetruecolor(32, 32);
        imagecopyresampled($amostra, $imagem, 0, 0, 0, 0, 32, 32, imagesx($imagem), imagesy($imagem));
        $soma = 0;
        for ($x = 0; $x < 32; $x++) {
            for ($y = 0; $y < 32; $y++) {
                $cor = imagecolorat($amostra, $x, $y);
                $soma += 0.299 * (($cor >> 16) & 0xFF) + 0.587 * (($cor >> 8) & 0xFF) + 0.114 * ($cor & 0xFF);
            }
        }

        return $soma / 1024;
    }
}
