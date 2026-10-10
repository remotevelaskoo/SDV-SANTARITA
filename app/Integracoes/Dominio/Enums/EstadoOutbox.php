<?php

namespace App\Integracoes\Dominio\Enums;

/** Estados da mensagem na outbox (ADR-004 §18.1). */
enum EstadoOutbox: string
{
    case Pendente = 'pendente';
    case Processando = 'processando';
    case Processado = 'processado';
    case FalhaTemporaria = 'falha_temporaria';
    case IntervencaoNecessaria = 'intervencao_necessaria';
    case Cancelado = 'cancelado';

    /** @return list<string> */
    public static function elegiveis(): array
    {
        return [self::Pendente->value, self::FalhaTemporaria->value];
    }
}
