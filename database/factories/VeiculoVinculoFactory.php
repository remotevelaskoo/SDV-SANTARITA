<?php

namespace Database\Factories;

use App\Models\Pessoa;
use App\Models\Veiculo;
use App\Models\VeiculoVinculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VeiculoVinculo>
 */
class VeiculoVinculoFactory extends Factory
{
    protected $model = VeiculoVinculo::class;

    public function definition(): array
    {
        return [
            'veiculo_id' => Veiculo::factory(),
            'pessoa_id' => Pessoa::factory(),
            'imovel_id' => null,
            'tipo' => 'proprietario',
            'status' => 'ativo',
            'started_at' => now(),
            'versao' => 1,
        ];
    }
}
