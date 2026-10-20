<?php

namespace App\Documentos\Infra;

use App\Documentos\Dominio\PortaOcrDocumento;
use App\Documentos\Dominio\ResultadoOcr;

/**
 * Simulador do OCR (SDV-OPE-018 §8). Não lê a imagem nem chama serviço
 * externo: devolve a resposta configurada para testes e, por padrão,
 * "documento não identificado", para que o operador digite os dados.
 * Nada do conteúdo da imagem é guardado ou registrado.
 */
class OcrSimulador implements PortaOcrDocumento
{
    private ?ResultadoOcr $proxima = null;

    public function responderCom(ResultadoOcr $resultado): void
    {
        $this->proxima = $resultado;
    }

    public function extrair(string $frente, ?string $verso = null, string $tipoDocumento = 'cnh'): ResultadoOcr
    {
        return $this->proxima ?? ResultadoOcr::naoReconhecido();
    }
}
