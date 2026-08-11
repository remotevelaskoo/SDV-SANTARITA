<?php

namespace App\Support;

class DestinationDirectory
{
    /** @var array<string, string> Imóvel de destino → responsável, usado quando o tipo de acesso exige um imóvel específico. */
    private const RESPONSIBLES = [
        'Bloco A — Apto 102' => 'Marcos Vinicius da Silva',
        'Bloco A — Apto 112' => 'Eduardo Nogueira',
        'Bloco A — Apto 208' => 'Bianca Moretti',
        'Bloco B — Apto 304' => 'Mariana Souza',
        'Bloco C — Apto 501' => 'Rafael Domingues',
    ];

    public static function responsibleFor(?string $property): ?string
    {
        if ($property === null) {
            return null;
        }

        return self::RESPONSIBLES[$property] ?? null;
    }

    /** @return list<string> */
    public static function options(): array
    {
        return array_keys(self::RESPONSIBLES);
    }
}
