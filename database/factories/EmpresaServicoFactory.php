<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\EmpresaServico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmpresaServico>
 */
class EmpresaServicoFactory extends Factory
{
    protected $model = EmpresaServico::class;

    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'atividade' => fake()->word(),
            'status' => 'autorizado',
        ];
    }
}
