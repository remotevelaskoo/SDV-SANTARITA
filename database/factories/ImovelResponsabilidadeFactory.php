<?php

namespace Database\Factories;

use App\Models\Imovel;
use App\Models\ImovelResponsabilidade;
use App\Models\Vinculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImovelResponsabilidade>
 */
class ImovelResponsabilidadeFactory extends Factory
{
    protected $model = ImovelResponsabilidade::class;

    public function definition(): array
    {
        return [
            'imovel_id' => Imovel::factory(),
            'vinculo_id' => Vinculo::factory(),
            'tipo' => 'responsavel_principal',
            'started_at' => now(),
        ];
    }
}
