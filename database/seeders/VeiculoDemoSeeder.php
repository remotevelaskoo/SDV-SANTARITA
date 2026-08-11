<?php

namespace Database\Seeders;

use App\Models\Imovel;
use App\Models\Implantacao;
use App\Models\Pessoa;
use App\Models\Veiculo;
use App\Models\VeiculoVinculo;
use Illuminate\Database\Seeder;

/**
 * Reaproveita os dois veículos de VehicleManagement.php (P12) cujo
 * proprietário já existe como Pessoa semeada (PessoaDemoSeeder). Os outros
 * três (DEF4G56, JKL2M34, MNO5P67) ficam fora porque seus donos
 * (Fernanda da Silva, Eduardo Nunes, Vale Serviços Ltda.) não existem como
 * Pessoa/Empresa real semeada — mesmo critério já usado no
 * VinculoDemoSeeder.
 */
class VeiculoDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(FoundationSeeder::class);

        if (Veiculo::query()->exists()) {
            return;
        }

        $implantacaoId = Implantacao::query()->where('slug', 'santa-rita')->value('id');

        $veiculos = [
            [
                'plate' => 'ABC1D23',
                'brand' => 'Toyota',
                'model' => 'Corolla XEi',
                'color' => 'Prata',
                'pessoa' => 'Marcos Vinicius da Silva',
                'imovel' => 'SRA-A-102',
            ],
            [
                'plate' => 'GHI7J89',
                'brand' => 'Volkswagen',
                'model' => 'T-Cross Comfortline',
                'color' => 'Cinza',
                'pessoa' => 'Bianca Moretti',
                'imovel' => 'SRA-A-208',
            ],
        ];

        foreach ($veiculos as $dados) {
            $pessoa = Pessoa::query()->where('nome', $dados['pessoa'])->first();
            $imovel = Imovel::query()->where('codigo', $dados['imovel'])->first();

            if ($pessoa === null || $imovel === null) {
                continue;
            }

            $veiculo = Veiculo::query()->create([
                'implantacao_id' => $implantacaoId,
                'plate_display' => $dados['plate'],
                'plate_normalized' => Veiculo::normalizePlate($dados['plate']),
                'country' => 'BR',
                'type' => 'carro',
                'brand' => $dados['brand'],
                'model' => $dados['model'],
                'color' => $dados['color'],
                'status' => 'ativo',
            ]);

            VeiculoVinculo::query()->create([
                'implantacao_id' => $implantacaoId,
                'veiculo_id' => $veiculo->id,
                'pessoa_id' => $pessoa->id,
                'imovel_id' => $imovel->id,
                'tipo' => 'proprietario',
                'status' => 'ativo',
                'started_at' => now()->subYear(),
                'versao' => 1,
            ]);
        }
    }
}
