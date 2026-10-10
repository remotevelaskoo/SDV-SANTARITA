<?php

namespace App\Integracoes\Dominio\Excecoes;

use DomainException;

/** Regra de negócio ou de segurança da integração impediu a operação. */
class RegraIntegracaoViolada extends DomainException
{
    public function __construct(public readonly string $codigo, string $mensagem)
    {
        parent::__construct($mensagem);
    }
}
