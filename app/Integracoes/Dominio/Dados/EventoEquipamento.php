<?php

namespace App\Integracoes\Dominio\Dados;

use DateTimeImmutable;

/**
 * Evento traduzido pelo adaptador para o formato interno. Imagem capturada
 * e template nunca entram aqui.
 */
final readonly class EventoEquipamento
{
    /** @param  array<string, scalar|null>  $dados */
    public function __construct(
        public string $idExterno,
        public string $tipo,
        public ?DateTimeImmutable $ocorridoEm,
        public ?string $direcao = null,
        public ?string $resultado = null,
        public ?string $referenciaCredencialExterna = null,
        public array $dados = [],
    ) {}
}
