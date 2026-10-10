<?php

namespace Tests\Support;

/**
 * Imagens sintéticas de "documento" para testar a avaliação de qualidade.
 * Nenhuma é documento real: retângulo claro com linhas escuras simulando texto.
 */
final class ImagensDocumentoSinteticas
{
    public static function boa(int $largura = 1000, int $altura = 650): string
    {
        return self::jpeg(self::desenhar($largura, $altura, 0.12));
    }

    public static function desfocada(): string
    {
        $imagem = self::desenhar(1000, 650, 0.12);
        for ($i = 0; $i < 40; $i++) {
            imagefilter($imagem, IMG_FILTER_GAUSSIAN_BLUR);
        }

        return self::jpeg($imagem);
    }

    public static function cortada(): string
    {
        return self::jpeg(self::desenhar(1000, 650, -0.08));
    }

    public static function escura(): string
    {
        $imagem = self::desenhar(1000, 650, 0.12);
        imagefilter($imagem, IMG_FILTER_BRIGHTNESS, -200);

        return self::jpeg($imagem);
    }

    public static function comReflexo(): string
    {
        $imagem = self::desenhar(1000, 650, 0.12);
        imagefilledellipse($imagem, 500, 325, 520, 300, imagecolorallocate($imagem, 255, 255, 255));

        return self::jpeg($imagem);
    }

    public static function pequena(): string
    {
        return self::jpeg(self::desenhar(320, 200, 0.12));
    }

    public static function vazia(): string
    {
        $imagem = imagecreatetruecolor(1000, 650);
        imagefill($imagem, 0, 0, imagecolorallocate($imagem, 128, 128, 128));

        return self::jpeg($imagem);
    }

    private static function desenhar(int $largura, int $altura, float $margem): \GdImage
    {
        $imagem = imagecreatetruecolor($largura, $altura);
        imagefill($imagem, 0, 0, imagecolorallocate($imagem, 70, 75, 80));
        $mx = (int) round($largura * $margem);
        $my = (int) round($altura * $margem);
        imagefilledrectangle($imagem, $mx, $my, $largura - 1 - $mx, $altura - 1 - $my, imagecolorallocate($imagem, 225, 225, 215));
        $preto = imagecolorallocate($imagem, 20, 20, 20);
        $inicioX = max(0, $mx) + (int) ($largura * 0.05);
        $fimX = min($largura - 1, $largura - $mx) - (int) ($largura * 0.05);
        for ($y = max(0, $my) + (int) ($altura * 0.08); $y < min($altura, $altura - $my) - 10; $y += (int) max(6, $altura * 0.06)) {
            for ($x = $inicioX; $x < $fimX; $x += 18) {
                imagefilledrectangle($imagem, $x, $y, $x + 10, $y + (int) max(3, $altura * 0.02), $preto);
            }
        }

        return $imagem;
    }

    private static function jpeg(\GdImage $imagem): string
    {
        ob_start();
        imagejpeg($imagem, null, 90);

        return (string) ob_get_clean();
    }
}
