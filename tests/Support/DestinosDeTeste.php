<?php

namespace Tests\Support;

use App\Models\Bloco;
use App\Models\Condominio;
use App\Models\Imovel;
use App\Models\ImovelResponsabilidade;
use App\Models\Pessoa;
use App\Models\Vinculo;

/**
 * Imóveis sintéticos com responsável principal no cadastro local, que é a
 * fonte do catálogo de destinos do pré-cadastro (CatalogoDestinos).
 * Exige o contexto de implantação já definido no teste.
 */
final class DestinosDeTeste
{
    public const PADRAO = [
        'Bloco B — Apto 304' => 'Mariana Souza',
        'Bloco A — Apto 102' => 'Marcos Vinicius da Silva',
        'Bloco A — Apto 208' => 'Bianca Moretti',
    ];

    /** @param array<string, ?string> $destinos rótulo "Bloco X — unidade" => responsável (null = sem responsável) */
    public static function criar(array $destinos = self::PADRAO): void
    {
        $condominio = Condominio::factory()->create();
        foreach ($destinos as $rotulo => $responsavel) {
            [$bloco, $unidade] = explode(' — ', $rotulo, 2);
            $blocoModel = Bloco::query()->firstOrCreate(['condominio_id' => $condominio->id, 'nome' => $bloco], ['ordem' => 1, 'status' => 'ativo']);
            $imovel = Imovel::factory()->create(['condominio_id' => $condominio->id, 'bloco_id' => $blocoModel->id, 'unidade' => $unidade]);
            if ($responsavel === null) {
                continue;
            }
            $vinculo = Vinculo::factory()->create(['imovel_id' => $imovel->id, 'pessoa_id' => Pessoa::factory()->create(['nome' => $responsavel])->id]);
            ImovelResponsabilidade::factory()->create(['imovel_id' => $imovel->id, 'vinculo_id' => $vinculo->id]);
        }
    }
}
