<?php

namespace App\Integracoes\Dominio\Excecoes;

/** Mesma chave idempotente com conteúdo diferente (ADR-005 §18.3). */
class PayloadDivergente extends RegraIntegracaoViolada
{
    public function __construct(string $chave)
    {
        parent::__construct('payload_divergente', "A chave idempotente {$chave} já foi usada com outro conteúdo.");
    }
}
