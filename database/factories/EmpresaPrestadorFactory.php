<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\EmpresaPrestador;
use App\Models\Pessoa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmpresaPrestador>
 */
class EmpresaPrestadorFactory extends Factory
{
    protected $model = EmpresaPrestador::class;

    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'pessoa_id' => Pessoa::factory(),
            'atividade' => fake()->jobTitle(),
            'status' => 'ativo',
            'started_at' => now(),
            'versao' => 1,
        ];
    }
}
