<?php

namespace Database\Seeders;

use App\Models\Catalogo;
use App\Models\CatalogoItem;
use App\Models\Implantacao;
use Illuminate\Database\Seeder;

/**
 * Catálogo global "motivos_negativa" (P17 fatia 4) + os 4 itens que hoje
 * estão hardcoded em `AccessValidation::denialReasonLabel()` — mesmos
 * textos, para não mudar o comportamento de quem já usa o sistema. Roda
 * antes de `MultiImplantacaoDemoSeeder` (só existe Santa Rita neste ponto,
 * então `Implantacao::current()` resolve sem ambiguidade).
 */
class CatalogoSeeder extends Seeder
{
    /** @var list<array{codigo: string, rotulo: string}> */
    private const MOTIVOS_NEGATIVA = [
        ['codigo' => 'sem_autorizacao', 'rotulo' => 'Sem autorização válida'],
        ['codigo' => 'documento_invalido', 'rotulo' => 'Documento inválido'],
        ['codigo' => 'vinculo_irregular', 'rotulo' => 'Vínculo irregular'],
        ['codigo' => 'decisao_operador', 'rotulo' => 'Decisão justificada do operador'],
    ];

    public function run(): void
    {
        $catalogo = Catalogo::query()->firstOrCreate(
            ['chave' => 'motivos_negativa'],
            ['rotulo' => 'Motivos de negativa de acesso'],
        );

        // DatabaseSeeder roda com WithoutModelEvents — o hook de
        // BelongsToImplantacao que atribuiria implantacao_id automaticamente
        // não dispara, então precisa ser passado explicitamente aqui (mesmo
        // padrão já usado em MultiImplantacaoDemoSeeder).
        $implantacaoId = Implantacao::current()->id;

        foreach (self::MOTIVOS_NEGATIVA as $ordem => $motivo) {
            CatalogoItem::query()->firstOrCreate(
                ['implantacao_id' => $implantacaoId, 'catalogo_id' => $catalogo->id, 'codigo' => $motivo['codigo']],
                ['rotulo' => $motivo['rotulo'], 'status' => 'ativo', 'ordem' => $ordem],
            );
        }
    }
}
