<?php

namespace Tests\Support;

/**
 * "Fotos de pessoa" sintéticas para o AJ-010: fundo e uma elipse clara no
 * lugar do rosto. Nenhuma é foto real; servem para formato, tamanho e luz.
 */
final class ImagensPessoaSinteticas
{
    public static function boa(int $largura = 800, int $altura = 600, int $tom = 170): string
    {
        $imagem = imagecreatetruecolor($largura, $altura);
        imagefill($imagem, 0, 0, imagecolorallocate($imagem, (int) ($tom * 0.6), (int) ($tom * 0.7), $tom));
        imagefilledellipse($imagem, intdiv($largura, 2), intdiv($altura, 2), intdiv($largura, 3), intdiv($altura * 2, 3), imagecolorallocate($imagem, min(255, $tom + 60), min(255, $tom + 40), min(255, $tom + 20)));

        return self::jpeg($imagem);
    }

    /** Mesma composição em outro tom: outra versão da foto. */
    public static function outra(): string
    {
        return self::boa(tom: 140);
    }

    public static function escura(): string
    {
        return self::boa(tom: 10);
    }

    public static function pequena(): string
    {
        return self::boa(200, 150);
    }

    private static function jpeg(\GdImage $imagem): string
    {
        ob_start();
        imagejpeg($imagem, null, 90);

        return (string) ob_get_clean();
    }
}
