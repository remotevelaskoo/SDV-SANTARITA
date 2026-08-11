<?php

namespace Database\Seeders;

use App\Models\Bloco;
use App\Models\Condominio;
use App\Models\EnderecoImovel;
use App\Models\Imovel;
use App\Models\Implantacao;
use Illuminate\Database\Seeder;

/**
 * Dados de demonstração dos imóveis, com os mesmos códigos e endereços já
 * usados no protótipo de PropertyManagement.php (P11), para que a futura
 * reconexão de telas (P21) encontre dados equivalentes.
 */
class ImovelDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(FoundationSeeder::class);

        if (Condominio::query()->exists()) {
            return;
        }

        $implantacaoId = Implantacao::query()->where('slug', 'santa-rita')->value('id');

        $condominio = Condominio::query()->create([
            'implantacao_id' => $implantacaoId,
            'nome' => 'Condomínio Santa Rita',
            'codigo' => 'SANTA-RITA',
            'status' => 'ativo',
        ]);

        $blocos = [];
        foreach (['A', 'B', 'C'] as $letra) {
            $blocos[$letra] = Bloco::query()->create([
                'implantacao_id' => $implantacaoId,
                'condominio_id' => $condominio->id,
                'nome' => "Bloco {$letra}",
                'ordem' => ord($letra) - ord('A') + 1,
                'status' => 'ativo',
            ]);
        }

        $imoveis = [
            ['codigo' => 'SRA-A-102', 'bloco' => 'A', 'unidade' => '102', 'address' => 'Rua das Acácias', 'number' => '100', 'district' => 'Jardim das Flores', 'status' => 'ativo'],
            ['codigo' => 'SRA-A-208', 'bloco' => 'A', 'unidade' => '208', 'address' => 'Rua das Acácias', 'number' => '100', 'district' => 'Jardim das Flores', 'status' => 'ativo'],
            ['codigo' => 'SRA-B-304', 'bloco' => 'B', 'unidade' => '304', 'address' => 'Rua das Acácias', 'number' => '120', 'district' => 'Jardim das Flores', 'status' => 'ativo'],
            ['codigo' => 'SRA-C-501', 'bloco' => 'C', 'unidade' => '501', 'address' => 'Rua das Palmeiras', 'number' => '50', 'district' => 'Jardim das Flores', 'status' => 'bloqueado'],
            ['codigo' => 'SRA-C-706', 'bloco' => 'C', 'unidade' => '706', 'address' => 'Rua das Palmeiras', 'number' => '50', 'district' => 'Jardim das Flores', 'status' => 'implantacao'],
        ];

        foreach ($imoveis as $dados) {
            $imovel = Imovel::query()->create([
                'implantacao_id' => $implantacaoId,
                'condominio_id' => $condominio->id,
                'bloco_id' => $blocos[$dados['bloco']]->id,
                'codigo' => $dados['codigo'],
                'unidade' => $dados['unidade'],
                'tipo' => 'apartamento',
                'status' => $dados['status'],
                'versao' => 1,
            ]);

            EnderecoImovel::query()->create([
                'implantacao_id' => $implantacaoId,
                'imovel_id' => $imovel->id,
                'zip_code' => '12010-000',
                'address' => $dados['address'],
                'address_number' => $dados['number'],
                'district' => $dados['district'],
                'city' => 'Taubaté',
                'state' => 'SP',
                'started_at' => now(),
            ]);
        }
    }
}
