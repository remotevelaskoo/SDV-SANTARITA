<?php

namespace App\Integracoes\Adaptadores\Simulador;

use App\Integracoes\Dominio\Contratos\PortaCameraSnapshot;
use App\Integracoes\Dominio\Dados\ContextoCamera;
use App\Integracoes\Dominio\Dados\ImagemCapturada;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;
use DateTimeImmutable;

/**
 * Simulador da câmera de placas (AJ-006). NÃO abre conexão de rede: desenha
 * uma imagem sintética com a faixa "SIMULADOR" e uma placa fictícia, para
 * validar o fluxo sem câmera. A captura sai com origem "simulador" e assim
 * aparece na tela, na evidência e na auditoria.
 */
class SimuladorCameraPlacas implements PortaCameraSnapshot
{
    public const PLACA_FICTICIA = 'SIM0A00';

    public function origem(): string
    {
        return 'simulador';
    }

    public function capturarSnapshot(ContextoCamera $camera): ImagemCapturada
    {
        if (! function_exists('imagecreatetruecolor')) {
            return ImagemCapturada::falha(ResultadoEquipamento::FalhaTecnica, 'simulador_indisponivel', 'O simulador precisa da extensão GD.');
        }

        $imagem = imagecreatetruecolor(640, 360);
        $fundo = imagecolorallocate($imagem, 52, 58, 70);
        $faixa = imagecolorallocate($imagem, 214, 40, 40);
        $branco = imagecolorallocate($imagem, 255, 255, 255);
        $placa = imagecolorallocate($imagem, 240, 240, 240);
        $azul = imagecolorallocate($imagem, 0, 51, 153);
        $preto = imagecolorallocate($imagem, 20, 20, 20);

        imagefilledrectangle($imagem, 0, 0, 639, 359, $fundo);
        imagefilledrectangle($imagem, 0, 0, 639, 44, $faixa);
        imagestring($imagem, 5, 16, 14, 'SIMULADOR - IMAGEM SINTETICA, SEM CAMERA REAL', $branco);
        imagefilledrectangle($imagem, 200, 170, 440, 250, $placa);
        imagefilledrectangle($imagem, 200, 170, 440, 186, $azul);
        imagestring($imagem, 2, 290, 172, 'BRASIL', $branco);
        imagestring($imagem, 5, 268, 205, self::PLACA_FICTICIA, $preto);
        imagestring($imagem, 3, 16, 330, 'Use somente para testar o fluxo. Nao serve como evidencia real.', $branco);

        ob_start();
        imagejpeg($imagem, null, 85);
        $conteudo = (string) ob_get_clean();

        return ImagemCapturada::capturada($conteudo, 'image/jpeg', new DateTimeImmutable);
    }
}
