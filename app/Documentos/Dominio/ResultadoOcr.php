<?php

namespace App\Documentos\Dominio;

/**
 * Campos sugeridos pelo OCR, cada um com a sua confiança (0 a 1).
 * Campos previstos: nome, cpf, numero_documento, data_nascimento, validade.
 */
final readonly class ResultadoOcr
{
    public const CAMPOS = ['nome', 'cpf', 'numero_documento', 'data_nascimento', 'validade'];

    /** @param  array<string, array{valor: ?string, confianca: float}>  $campos */
    public function __construct(
        public bool $reconhecido,
        public array $campos = [],
        public ?string $mensagem = null,
    ) {}

    public static function naoReconhecido(string $mensagem = 'Documento não identificado. Faça nova captura ou digite os dados.'): self
    {
        return new self(false, [], $mensagem);
    }

    public function valor(string $campo): ?string
    {
        return $this->campos[$campo]['valor'] ?? null;
    }

    public function confianca(string $campo): float
    {
        return (float) ($this->campos[$campo]['confianca'] ?? 0.0);
    }

    /** @return list<string> campos lidos abaixo da confiança mínima */
    public function camposParaConferencia(float $minima): array
    {
        return array_values(array_filter(
            array_keys($this->campos),
            fn (string $campo) => $this->valor($campo) !== null && $this->confianca($campo) < $minima,
        ));
    }
}
