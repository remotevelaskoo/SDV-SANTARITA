<?php

namespace App\Integracoes\Dominio\Enums;

/**
 * No recorte da ADR-016 cada terminal aciona uma cancela ou uma catraca.
 * `bancada` identifica o ponto de testes da homologação, sem carga ligada
 * ao relé: nunca é ativado e não atende operação real.
 */
enum TipoPontoAcesso: string
{
    case Cancela = 'cancela';
    case Catraca = 'catraca';
    case Bancada = 'bancada';
}
