<?php

namespace App\Integracoes\Dominio\Dados;

use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;
use DateTimeImmutable;
use LogicException;

/**
 * Imagem estática devolvida pela câmera do terminal para conferência visual
 * (bancada e diagnóstico). O conteúdo fica somente em memória: não aparece
 * em var_dump/print_r/logs e não pode ser serializado para fila, cache ou
 * outbox. Não é credencial biométrica (ADR-013).
 */
final class ImagemCapturada
{
    private function __construct(
        public readonly ResultadoEquipamento $resultado,
        public readonly ?string $codigo,
        public readonly ?string $mensagem,
        private readonly ?string $conteudo,
        public readonly ?string $tipoMime,
        public readonly ?DateTimeImmutable $capturadaEm,
    ) {}

    public static function capturada(string $conteudo, string $tipoMime, DateTimeImmutable $capturadaEm): self
    {
        return new self(ResultadoEquipamento::Confirmado, null, null, $conteudo, $tipoMime, $capturadaEm);
    }

    public static function falha(ResultadoEquipamento $resultado, string $codigo, string $mensagem): self
    {
        return new self($resultado, $codigo, $mensagem, null, null, null);
    }

    public static function capacidadeAusente(string $motivo): self
    {
        $ausente = ResultadoOperacao::capacidadeAusente(Capacidade::CapturarImagem, $motivo);

        return new self($ausente->resultado, $ausente->codigo, $ausente->mensagem, null, null, null);
    }

    public function conteudo(): ?string
    {
        return $this->conteudo;
    }

    public function tamanhoBytes(): int
    {
        return $this->conteudo === null ? 0 : strlen($this->conteudo);
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return [
            'resultado' => $this->resultado->value,
            'codigo' => $this->codigo,
            'tipoMime' => $this->tipoMime,
            'tamanhoBytes' => $this->tamanhoBytes(),
            'conteudo' => '[IMAGEM OMITIDA]',
        ];
    }

    public function __serialize(): array
    {
        throw new LogicException('Imagem capturada não pode ser serializada.');
    }

    public function __unserialize(array $data): void
    {
        throw new LogicException('Imagem capturada não pode ser desserializada.');
    }
}
