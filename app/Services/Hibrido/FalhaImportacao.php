<?php

namespace App\Services\Hibrido;

use RuntimeException;

/** Falha conhecida na importação do portal, sem conteúdo pessoal na mensagem. */
class FalhaImportacao extends RuntimeException
{
    /** @param list<string> $campos */
    public function __construct(public readonly string $codigo, public readonly array $campos = [])
    {
        parent::__construct('Falha de importação: '.$codigo);
    }
}
