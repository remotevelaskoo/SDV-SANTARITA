<?php

namespace App\Documentos\Infra;

use App\Documentos\Dominio\PortaOcrDocumento;
use App\Documentos\Dominio\ResultadoOcr;
use App\Services\DocumentOcrClient;
use RuntimeException;

/**
 * Adaptador do cadastro rápido para o leitor interno de documentos do
 * AJ-005 (serviço Python document-ocr, ADR-011, SDV-UXP-019). Reaproveita o
 * mesmo cliente do pré-cadastro: as imagens seguem só pela rede interna e o
 * recorte da foto não é solicitado, porque o cadastro rápido não guarda a
 * captura.
 *
 * Falha ou indisponibilidade do leitor viram "não reconhecido": o operador
 * digita os dados, nunca fica bloqueado.
 */
class OcrServicoDocumento implements PortaOcrDocumento
{
    /** Campos do serviço => campos do SDV. */
    private const MAPA = [
        'name' => 'nome',
        'cpf' => 'cpf',
        'birth_date' => 'data_nascimento',
        'document_number' => 'numero_documento',
    ];

    public function __construct(private DocumentOcrClient $cliente) {}

    public function extrair(string $frente, ?string $verso = null, string $tipoDocumento = 'cnh'): ResultadoOcr
    {
        if (! $this->cliente->enabled()) {
            return ResultadoOcr::naoReconhecido('A leitura automática não está ativa neste ambiente. Digite os dados.');
        }

        try {
            $resposta = $this->cliente->extractContents($tipoDocumento, $frente, $verso);
        } catch (RuntimeException) {
            return ResultadoOcr::naoReconhecido('A leitura automática não está disponível agora. Faça nova captura ou digite os dados.');
        }

        $campos = [];
        foreach (self::MAPA as $origem => $destino) {
            $campo = $resposta['fields'][$origem] ?? null;
            $valor = is_array($campo) && is_string($campo['value'] ?? null) && $campo['value'] !== '' ? $campo['value'] : null;
            if ($valor === null) {
                continue;
            }
            // Sem confiança informada, o campo sempre vai para conferência.
            $campos[$destino] = ['valor' => $valor, 'confianca' => is_numeric($campo['confidence'] ?? null) ? (float) $campo['confidence'] : 0.0];
        }

        return $campos === [] ? ResultadoOcr::naoReconhecido() : new ResultadoOcr(true, $campos);
    }
}
