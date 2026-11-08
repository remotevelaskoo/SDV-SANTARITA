<?php

namespace App\Integracoes\Dominio\Dados;

use LogicException;

/**
 * Fotografia atual de uma pessoa a ser associada ao seu cadastro num
 * terminal facial (AJ-010, ADR-013 1.1.0).
 *
 * Só existe depois de o SDV decidir que a pessoa tem autorização vigente e
 * de o cadastro (usuário) já estar gravado no terminal. O conteúdo é lido do
 * armazenamento privado pelo worker e fica somente em memória: não aparece
 * em var_dump/print_r/logs e não pode ser serializado para fila, cache ou
 * outbox. Não é template biométrico: o terminal é quem extrai o modelo.
 */
final class FotoFacialParaEnviar
{
    public function __construct(
        public readonly string $credencialId,
        public readonly string $versaoFoto,
        private readonly string $conteudo,
        public readonly string $tipoMime,
        public readonly ?string $idExternoAtual = null,
    ) {}

    public function conteudo(): string
    {
        return $this->conteudo;
    }

    public function tamanhoBytes(): int
    {
        return strlen($this->conteudo);
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return [
            'credencialId' => $this->credencialId,
            'versaoFoto' => $this->versaoFoto,
            'tipoMime' => $this->tipoMime,
            'tamanhoBytes' => $this->tamanhoBytes(),
            'conteudo' => '[IMAGEM OMITIDA]',
        ];
    }

    public function __serialize(): array
    {
        throw new LogicException('Foto facial não pode ser serializada.');
    }

    public function __unserialize(array $data): void
    {
        throw new LogicException('Foto facial não pode ser desserializada.');
    }
}
