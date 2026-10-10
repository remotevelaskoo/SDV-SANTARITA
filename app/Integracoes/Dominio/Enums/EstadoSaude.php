<?php

namespace App\Integracoes\Dominio\Enums;

/** Saúde técnica observada do equipamento (docs/008 §22.3). */
enum EstadoSaude: string
{
    case Desconhecido = 'desconhecido';
    case Conectado = 'conectado';
    case Degradado = 'degradado';
    case Indisponivel = 'indisponivel';
    case CredencialInvalida = 'credencial_invalida';
}
