<?php

namespace App\Integracoes\Dominio\Dados;

use App\Integracoes\Dominio\Enums\Direcao;
use Closure;

/**
 * Tudo o que um adaptador precisa saber sobre o equipamento, sem models
 * Laravel (ADR-007 §7). A credencial é obtida sob demanda e nunca fica
 * guardada neste objeto.
 */
final readonly class ContextoEquipamento
{
    /**
     * @param  Closure(): ?SegredoTecnico  $obterSegredo
     * @param  string  $esquema  'http' ou 'https'
     * @param  ?string  $tlsPinSha256  SHA-256 em base64 da chave pública confiada pelo operador
     */
    public function __construct(
        public string $implantacaoId,
        public string $equipamentoId,
        public ?string $pontoAcessoId,
        public string $fabricante,
        public string $modelo,
        public ?string $firmwareVersao,
        public string $enderecoRede,
        public ?int $portaRede,
        public string $protocolo,
        public Direcao $direcao,
        public int $timeoutSegundos,
        public ?string $usuarioTecnico,
        private Closure $obterSegredo,
        public string $correlationId,
        public string $esquema = 'https',
        public ?string $tlsPinSha256 = null,
    ) {}

    public function segredo(): ?SegredoTecnico
    {
        return ($this->obterSegredo)();
    }
}
