<?php

namespace App\Support;

use App\Models\Implantacao;
use RuntimeException;

/**
 * Resolve a implantação ativa do processo atual.
 *
 * Hoje o SDV Access tem uma única implantação real (Santa Rita), então a
 * resolução é simples: a implantação semeada no banco. A resolução por
 * subdomínio, sessão ou seleção do usuário (ADR-002, seção 11) pertence a
 * P19/P20/P21, que ainda não existem — este helper só garante que o modelo
 * de dados já seja implantação-aware desde já, sem inventar um pipeline de
 * contexto que não tem onde se conectar ainda.
 */
class ImplantacaoContext
{
    private static ?Implantacao $override = null;

    private static bool $overridden = false;

    public static function current(): Implantacao
    {
        if (self::$overridden) {
            if (self::$override === null) {
                throw new RuntimeException('Nenhuma implantação ativa no contexto de teste. Use ImplantacaoContext::setCurrentForTesting().');
            }

            return self::$override;
        }

        $implantacao = Implantacao::query()->where('status', 'ativa')->first();

        if ($implantacao === null) {
            throw new RuntimeException('Nenhuma implantação ativa encontrada. Rode o seeder de fundação antes de usar entidades operacionais.');
        }

        return $implantacao;
    }

    public static function setCurrentForTesting(?Implantacao $implantacao): void
    {
        self::$overridden = true;
        self::$override = $implantacao;
    }

    public static function forgetCurrent(): void
    {
        self::$overridden = false;
        self::$override = null;
    }
}
