<?php

namespace App\Integracoes\Dominio\Dados;

use App\Integracoes\Dominio\Enums\Direcao;
use DateTimeImmutable;

/**
 * Credencial elegível a ser distribuída a um equipamento.
 *
 * O SDV é quem decide a elegibilidade (RN-040): este objeto só existe
 * depois dessa decisão. Não carrega imagem, template nem documento; a
 * credencial facial está bloqueada pela ADR-013 e é recusada antes de
 * chegar ao adaptador.
 */
final readonly class CredencialParaSincronizar
{
    public function __construct(
        public string $credencialId,
        public string $tipo,
        public string $titularReferencia,
        public string $nomeExibicao,
        public DateTimeImmutable $vigenciaInicio,
        public ?DateTimeImmutable $vigenciaFim,
        public Direcao $direcao,
        public ?string $idExternoAtual = null,
    ) {}

    public function ehFacial(): bool
    {
        return $this->tipo === 'face';
    }
}
