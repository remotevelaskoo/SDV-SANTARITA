<?php

namespace App\Integracoes\Infra;

use App\Integracoes\Dominio\Contratos\PortaEquipamentoAcesso;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use Illuminate\Contracts\Container\Container;

/**
 * Resolve o adaptador pelo código gravado no equipamento. O núcleo nunca
 * referencia uma classe de fabricante diretamente (ADR-007 §3, CA-ADR-007-001).
 */
class RegistroAdaptadores
{
    public function __construct(private Container $container) {}

    /** @return list<string> */
    public function codigos(): array
    {
        return array_keys((array) config('integracoes.adaptadores'));
    }

    public function existe(string $codigo): bool
    {
        return array_key_exists($codigo, (array) config('integracoes.adaptadores'));
    }

    public function permitido(string $codigo): bool
    {
        return $this->existe($codigo)
            && ($codigo !== 'simulador' || (bool) config('integracoes.simulador_permitido'));
    }

    public function para(string $codigo): PortaEquipamentoAcesso
    {
        if (! $this->permitido($codigo)) {
            throw new RegraIntegracaoViolada('adaptador_indisponivel', "O adaptador '{$codigo}' não está disponível neste ambiente.");
        }

        $adaptador = $this->container->make(config("integracoes.adaptadores.{$codigo}"));

        if (! $adaptador instanceof PortaEquipamentoAcesso) {
            throw new RegraIntegracaoViolada('adaptador_invalido', "O adaptador '{$codigo}' não implementa a porta de equipamentos.");
        }

        return $adaptador;
    }
}
