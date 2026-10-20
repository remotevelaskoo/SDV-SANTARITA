<?php

namespace App\Documentos\Dominio;

/**
 * Porta de leitura de documentos (SDV-OPE-018 §8). O núcleo não conhece o
 * motor de OCR: nesta entrega só existe o simulador; um adaptador real
 * (local ou serviço Python/FastAPI, ADR-011) depende de decisão
 * arquitetural, contratual e LGPD. Com o AJ-005, o adaptador
 * OcrServicoDocumento usa o leitor interno (serviço Python, SDV-UXP-019)
 * quando DOCUMENT_OCR_ENABLED estiver ligado.
 *
 * O resultado é SUGESTÃO: nunca aprova cadastro, cria autorização, libera
 * acesso ou vira biometria.
 */
interface PortaOcrDocumento
{
    public const TIPOS_DOCUMENTO = ['cnh', 'rg_cin'];

    /** Conteúdo binário das imagens, mantido só em memória durante a chamada. */
    public function extrair(string $frente, ?string $verso = null, string $tipoDocumento = 'cnh'): ResultadoOcr;
}
