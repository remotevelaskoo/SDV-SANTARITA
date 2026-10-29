<?php

namespace App\Integracoes\Dominio\Contratos;

use App\Integracoes\Dominio\Dados\ContextoCamera;
use App\Integracoes\Dominio\Dados\ImagemCapturada;

/**
 * Porta das câmeras de placas (AJ-006, fatia 5): uma imagem estática sob
 * demanda, sem transmissão contínua. O núcleo não conhece fabricante, URL
 * de fábrica nem protocolo de vídeo.
 *
 * - snapshot HTTP/HTTPS: o adaptador chama o caminho cadastrado na câmera;
 * - RTSP/ONVIF: atendido por um gateway de vídeo na rede interna que expõe
 *   o quadro atual por HTTP; o SDV fala só com o gateway, pela mesma porta;
 * - simulador: imagem sintética, claramente marcada, sem conexão nenhuma.
 *
 * Leitura da placa (LPR/OCR) é outra etapa e não faz parte desta porta.
 */
interface PortaCameraSnapshot
{
    public function capturarSnapshot(ContextoCamera $camera): ImagemCapturada;

    /** "camera" para imagem real do equipamento; "simulador" para imagem sintética. */
    public function origem(): string;
}
