<?php

namespace App\Integracoes\Dominio\Dados;

use DateTimeImmutable;

/**
 * Contrato mínimo do comando de abertura (ADR-007 §7). Só é montado
 * depois de uma decisão de acesso favorável registrada pelo SDV.
 */
final readonly class ComandoAbertura
{
    public function __construct(
        public string $comandoId,
        public string $pontoAcessoId,
        public string $decisaoReferencia,
        public string $chaveIdempotencia,
        public DateTimeImmutable $solicitadoEm,
        public DateTimeImmutable $expiraEm,
        public string $origem,
        public ?int $atorId,
    ) {}
}
