<?php

namespace App\Integracoes\Dominio\Enums;

/** Situação administrativa do equipamento (docs/008 §22.3). */
enum StatusEquipamento: string
{
    case NaoConfigurado = 'nao_configurado';
    case Configurando = 'configurando';
    case Ativo = 'ativo';
    case Manutencao = 'manutencao';
    case Inativo = 'inativo';
}
