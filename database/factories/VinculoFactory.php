<?php

namespace Database\Factories;

use App\Models\Imovel;
use App\Models\Pessoa;
use App\Models\Vinculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vinculo>
 */
class VinculoFactory extends Factory
{
    protected $model = Vinculo::class;

    public function definition(): array
    {
        return [
            'pessoa_id' => Pessoa::factory(),
            'imovel_id' => Imovel::factory(),
            'tipo' => 'morador',
            'papel' => 'titular',
            'status' => 'ativo',
            'origem' => 'cadastro_manual',
            'started_at' => now(),
            'versao' => 1,
        ];
    }
}
