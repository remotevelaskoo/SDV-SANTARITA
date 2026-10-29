<?php

namespace App\Integracoes\Dominio\Enums;

/**
 * Situação de cada capacidade para um firmware específico (matriz de
 * homologação, ADR-016 CA-ADR-016-008). Uma versão de firmware nunca é
 * "compatível" como um todo: cada capacidade tem o seu estado.
 */
enum EstadoHomologacao: string
{
    // O adaptador não tem código para esta capacidade.
    case NaoImplementada = 'nao_implementada';
    // O terminal informa que não oferece a capacidade.
    case NaoSuportada = 'nao_suportada';
    // O terminal informa que oferece, mas nada foi validado em bancada.
    case Detectada = 'detectada';
    // Validada em parte; só executa com o terminal em homologação (bancada).
    case EmHomologacao = 'em_homologacao';
    // Validada no terminal real, com evidência registrada.
    case Homologada = 'homologada';
    // Proibida por decisão (ex.: biometria antes do ADR-013).
    case Bloqueada = 'bloqueada';

    public function executavel(): bool
    {
        return in_array($this, [self::EmHomologacao, self::Homologada], true);
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::NaoImplementada => 'Não implementada',
            self::NaoSuportada => 'Não suportada',
            self::Detectada => 'Detectada',
            self::EmHomologacao => 'Em homologação',
            self::Homologada => 'Homologada',
            self::Bloqueada => 'Bloqueada',
        };
    }
}
