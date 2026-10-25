<?php

namespace App\Documentos\Dominio;

use RuntimeException;

/**
 * Falha ao obter a leitura automática, já classificada (AJ-006): a mensagem
 * técnica nunca chega ao usuário; o SDV mostra a orientação da categoria.
 *
 * Categorias: comunicacao (sem resposta, tempo esgotado), servico (leitor
 * desligado, sem configuração, recusou a autenticação, erro interno ou
 * resposta inválida) e captura (o leitor recusou o arquivo enviado).
 */
class FalhaLeituraDocumento extends RuntimeException
{
    public function __construct(
        public readonly string $categoria,
        public readonly string $codigo,
        string $mensagem = '',
    ) {
        parent::__construct($mensagem !== '' ? $mensagem : "{$categoria}:{$codigo}");
    }
}
