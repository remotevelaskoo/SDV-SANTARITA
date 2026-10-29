<?php

namespace App\Integracoes\Dominio\Enums;

/** Tipos de equipamento (docs/008 §22.1). Esta fundação cobre o terminal facial. */
enum TipoEquipamento: string
{
    case TerminalFacial = 'terminal_facial';
}
