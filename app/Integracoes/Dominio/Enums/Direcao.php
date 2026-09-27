<?php

namespace App\Integracoes\Dominio\Enums;

/** Direção operacional (docs/008 §21.2). */
enum Direcao: string
{
    case Entrada = 'entrada';
    case Saida = 'saida';
    case Bidirecional = 'bidirecional';

    public function compativelCom(self $suportadaPeloPonto): bool
    {
        return $suportadaPeloPonto === self::Bidirecional || $suportadaPeloPonto === $this;
    }
}
