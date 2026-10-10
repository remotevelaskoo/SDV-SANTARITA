<?php

namespace App\Integracoes\Dominio\Dados;

use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\EstadoHomologacao;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;

/**
 * Capacidades declaradas por um adaptador para um equipamento específico,
 * versionadas pelo contrato e pelo firmware (ADR-007 §6).
 *
 * `suportadas` são as executáveis (homologadas ou em homologação);
 * `estados` traz a matriz completa, capacidade a capacidade.
 */
final readonly class CapacidadesDeclaradas
{
    /**
     * @param  list<Capacidade>  $suportadas
     * @param  array<string, string>  $motivosAusencia  capacidade => motivo
     * @param  array<string, EstadoHomologacao>  $estados  capacidade => estado
     */
    public function __construct(
        public ResultadoEquipamento $resultado,
        public string $versaoContrato,
        public ?string $firmwareVersao,
        public array $suportadas,
        public array $motivosAusencia = [],
        public ?string $mensagem = null,
        public bool $consultadoNoEquipamento = false,
        public array $estados = [],
    ) {}

    public function suporta(Capacidade $capacidade): bool
    {
        return in_array($capacidade, $this->suportadas, true);
    }

    public function estado(Capacidade $capacidade): EstadoHomologacao
    {
        return $this->estados[$capacidade->value]
            ?? ($this->suporta($capacidade) ? EstadoHomologacao::Homologada : EstadoHomologacao::NaoImplementada);
    }
}
