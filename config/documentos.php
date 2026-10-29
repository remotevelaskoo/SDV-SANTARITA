<?php

use App\Documentos\Infra\OcrServicoDocumento;
use App\Documentos\Infra\OcrSimulador;

/*
|--------------------------------------------------------------------------
| Cadastro rápido assistido por câmera (SDV-OPE-018)
|--------------------------------------------------------------------------
|
| Com DOCUMENT_OCR_ENABLED ligado, usa o leitor interno do AJ-005 (serviço
| Python na rede interna, SDV-UXP-019); desligado, usa o simulador. Nenhum
| OCR de terceiros. Limiares provisórios até
| a homologação das câmeras e das estações (SDV-OPE-018 §11).
|
*/

return [

    // Classe que implementa App\Documentos\Dominio\PortaOcrDocumento.
    'ocr' => [
        'adaptador' => env('DOCUMENT_OCR_ENABLED', false) ? OcrServicoDocumento::class : OcrSimulador::class,
        // Abaixo desta confiança o campo fica como "Requer conferência".
        'confianca_minima' => (float) env('SDV_OCR_CONFIANCA_MINIMA', 0.85),
    ],

    // Retenção das capturas: sem política aprovada, nada é persistido. O
    // arquivo temporário do upload é apagado logo depois da análise.
    'captura' => [
        'retencao' => env('SDV_CAPTURA_DOCUMENTO_RETENCAO', 'nao_persistir'),
        'tamanho_maximo_kb' => 6144,
    ],

    'qualidade' => [
        'resolucao_minima' => 480,
        'brilho_minimo' => 50,
        'brilho_maximo' => 225,
        'reflexo_maximo' => 0.04,
        'nitidez_minima' => 300,
        'direcionalidade_maxima' => 4,
        'bordas_minimas' => 0.01,
        'bordas_na_margem_maxima' => 0.12,
    ],

];
