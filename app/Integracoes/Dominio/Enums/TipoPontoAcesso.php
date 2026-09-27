<?php

namespace App\Integracoes\Dominio\Enums;

/** No recorte da ADR-016 cada terminal aciona uma cancela ou uma catraca. */
enum TipoPontoAcesso: string
{
    case Cancela = 'cancela';
    case Catraca = 'catraca';
}
