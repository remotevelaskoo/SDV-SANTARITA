<?php

namespace App\Integracoes\Dominio\Dados;

use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;

/**
 * Resultado devolvido pela porta. `dados` só contém valores escalares já
 * sanitizados pelo adaptador (nunca payload bruto, segredo, imagem ou
 * template). `idExterno` é o identificador que o equipamento atribuiu,
 * sempre tratado como texto e secundário ao UUID interno.
 */
final readonly class ResultadoOperacao
{
    /** @param  array<string, scalar|null>  $dados */
    public function __construct(
        public ResultadoEquipamento $resultado,
        public ?string $codigo = null,
        public ?string $mensagem = null,
        public ?string $idExterno = null,
        public array $dados = [],
        public ?int $latenciaMs = null,
    ) {}

    public static function capacidadeAusente(Capacidade $capacidade, string $motivo): self
    {
        return new self(ResultadoEquipamento::CapacidadeAusente, 'capacidade_ausente', "{$capacidade->value}: {$motivo}");
    }

    public static function confirmado(?string $idExterno = null, array $dados = []): self
    {
        return new self(ResultadoEquipamento::Confirmado, idExterno: $idExterno, dados: $dados);
    }

    public static function desconhecido(string $codigo, string $mensagem): self
    {
        return new self(ResultadoEquipamento::ConfirmacaoDesconhecida, $codigo, $mensagem);
    }
}
