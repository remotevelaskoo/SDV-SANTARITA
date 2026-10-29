<?php

namespace App\Integracoes\Dominio\Enums;

/** Tipos de equipamento (docs/008 §22.1): terminal facial e, desde o AJ-006, câmera de placas. */
enum TipoEquipamento: string
{
    case TerminalFacial = 'terminal_facial';
    // Imagem estática sob demanda para conferência da placa; não aciona relé nem recebe credencial.
    case CameraPlacas = 'camera_placas';
}
