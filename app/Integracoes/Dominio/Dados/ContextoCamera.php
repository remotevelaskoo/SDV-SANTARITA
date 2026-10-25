<?php

namespace App\Integracoes\Dominio\Dados;

use Closure;

/**
 * O que o adaptador de câmera precisa saber, sem models (AJ-006). Endereço,
 * porta, caminho e credencial ficam só no backend; a senha é obtida sob
 * demanda e nunca guardada neste objeto.
 */
final readonly class ContextoCamera
{
    /** @param  Closure(): ?SegredoTecnico  $obterSegredo */
    public function __construct(
        public string $implantacaoId,
        public string $equipamentoId,
        public string $esquema,
        public string $enderecoRede,
        public ?int $portaRede,
        public string $caminhoSnapshot,
        public int $timeoutSegundos,
        public ?string $usuarioTecnico,
        private Closure $obterSegredo,
        public string $correlationId,
        public ?string $tlsPinSha256 = null,
        public int $tamanhoMaximoBytes = 3_145_728,
    ) {}

    public function segredo(): ?SegredoTecnico
    {
        return ($this->obterSegredo)();
    }
}
