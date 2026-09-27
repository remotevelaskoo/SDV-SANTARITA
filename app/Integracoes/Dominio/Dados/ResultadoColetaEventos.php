<?php

namespace App\Integracoes\Dominio\Dados;

use App\Integracoes\Dominio\Enums\ResultadoEquipamento;

final readonly class ResultadoColetaEventos
{
    /**
     * @param  list<EventoEquipamento>  $eventos
     * @param  int  $descartadosInvalidos  eventos recusados pela validação do adaptador
     */
    public function __construct(
        public ResultadoEquipamento $resultado,
        public array $eventos = [],
        public ?string $cursor = null,
        public int $descartadosInvalidos = 0,
        public ?string $mensagem = null,
    ) {}
}
