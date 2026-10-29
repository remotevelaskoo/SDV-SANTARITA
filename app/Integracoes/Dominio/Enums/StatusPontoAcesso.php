<?php

namespace App\Integracoes\Dominio\Enums;

/** Situação do ponto de acesso (docs/008 §21.3). */
enum StatusPontoAcesso: string
{
    case EmImplantacao = 'em_implantacao';
    case Ativo = 'ativo';
    case Manutencao = 'manutencao';
    case Indisponivel = 'indisponivel';
    case Inativo = 'inativo';
}
