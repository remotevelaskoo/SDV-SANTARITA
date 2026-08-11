<?php

namespace Database\Seeders;

use App\Models\Imovel;
use App\Models\ImovelResponsabilidade;
use App\Models\Implantacao;
use App\Models\Pessoa;
use App\Models\Vinculo;
use Illuminate\Database\Seeder;

/**
 * Liga as pessoas e imóveis já semeados (PessoaDemoSeeder, ImovelDemoSeeder)
 * usando o mesmo mapeamento de responsável já usado em
 * App\Support\DestinationDirectory. Eduardo Nogueira (mapeado para o
 * "Bloco A — Apto 112" no DestinationDirectory) fica sem vínculo aqui,
 * porque esse código não existe entre os imóveis semeados — inconsistência
 * de dados de demonstração pré-existente, não algo criado por esta fatia.
 */
class VinculoDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(FoundationSeeder::class);

        if (Vinculo::query()->exists()) {
            return;
        }

        $implantacaoId = Implantacao::query()->where('slug', 'santa-rita')->value('id');

        $pares = [
            'Marcos Vinicius da Silva' => 'SRA-A-102',
            'Bianca Moretti' => 'SRA-A-208',
            'Mariana Souza' => 'SRA-B-304',
            'Rafael Domingues' => 'SRA-C-501',
        ];

        foreach ($pares as $nomePessoa => $codigoImovel) {
            $pessoa = Pessoa::query()->where('nome', $nomePessoa)->first();
            $imovel = Imovel::query()->where('codigo', $codigoImovel)->first();

            if ($pessoa === null || $imovel === null) {
                continue;
            }

            $vinculo = Vinculo::query()->create([
                'implantacao_id' => $implantacaoId,
                'pessoa_id' => $pessoa->id,
                'imovel_id' => $imovel->id,
                'tipo' => 'proprietario',
                'papel' => 'titular',
                'status' => 'ativo',
                'origem' => 'cadastro_manual',
                'started_at' => now()->subYear(),
                'versao' => 1,
            ]);

            ImovelResponsabilidade::query()->create([
                'implantacao_id' => $implantacaoId,
                'imovel_id' => $imovel->id,
                'vinculo_id' => $vinculo->id,
                'tipo' => 'responsavel_principal',
                'started_at' => now()->subYear(),
            ]);
        }
    }
}
