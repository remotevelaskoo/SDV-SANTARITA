<?php

namespace App\Integracoes\Dominio\Enums;

/** Situação administrativa do equipamento (docs/008 §22.3). */
enum StatusEquipamento: string
{
    case NaoConfigurado = 'nao_configurado';
    case Configurando = 'configurando';
    // Bancada (ADR-016 §11): diagnóstico, captura e teste do relé, sem operação real.
    case EmHomologacao = 'em_homologacao';
    case Ativo = 'ativo';
    case Manutencao = 'manutencao';
    case Inativo = 'inativo';

    public function rotulo(): string
    {
        return match ($this) {
            self::NaoConfigurado => 'Não configurado',
            self::Configurando => 'Configurando',
            self::EmHomologacao => 'Em homologação',
            self::Ativo => 'Ativo',
            self::Manutencao => 'Manutenção',
            self::Inativo => 'Inativo',
        };
    }
}
