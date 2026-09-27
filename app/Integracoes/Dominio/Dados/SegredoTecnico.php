<?php

namespace App\Integracoes\Dominio\Dados;

use LogicException;
use SensitiveParameter;
use Stringable;

/**
 * Valor de segredo técnico mantido somente em memória durante a chamada ao
 * adaptador (ADR-009 §9). Não se converte em texto, não aparece em
 * var_dump/print_r/logs e não pode ser serializado para fila ou cache.
 */
final class SegredoTecnico implements Stringable
{
    public function __construct(#[SensitiveParameter] private readonly string $valor) {}

    public function revelar(): string
    {
        return $this->valor;
    }

    public function __toString(): string
    {
        return '[SEGREDO PROTEGIDO]';
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['valor' => '[SEGREDO PROTEGIDO]'];
    }

    public function __serialize(): array
    {
        throw new LogicException('Segredo técnico não pode ser serializado.');
    }

    public function __unserialize(array $data): void
    {
        throw new LogicException('Segredo técnico não pode ser desserializado.');
    }
}
