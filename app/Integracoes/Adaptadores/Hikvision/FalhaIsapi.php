<?php

namespace App\Integracoes\Adaptadores\Hikvision;

use App\Integracoes\Dominio\Dados\ResultadoOperacao;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;
use RuntimeException;

/**
 * Falha já classificada de uma chamada ISAPI. A mensagem é escrita por este
 * adaptador e nunca repete URL, cabeçalho, corpo ou segredo.
 */
final class FalhaIsapi extends RuntimeException
{
    public function __construct(
        public readonly ResultadoEquipamento $resultado,
        public readonly string $codigo,
        string $mensagem,
        public readonly ?int $statusHttp = null,
    ) {
        parent::__construct($mensagem);
    }

    public function comoResultado(): ResultadoOperacao
    {
        return new ResultadoOperacao(
            $this->resultado,
            $this->codigo,
            $this->getMessage(),
            dados: $this->statusHttp === null ? [] : ['status_http' => $this->statusHttp],
        );
    }

    /** O pedido comprovadamente não chegou ao terminal. */
    public function naoAlcancou(): bool
    {
        return in_array($this->codigo, ['rede_inalcancavel', 'timeout_conexao', 'certificado_invalido', 'credencial_ausente', 'endereco_invalido'], true);
    }
}
