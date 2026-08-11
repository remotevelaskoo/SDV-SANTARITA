<?php

namespace Database\Factories;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Empresa>
 */
class EmpresaFactory extends Factory
{
    protected $model = Empresa::class;

    public function definition(): array
    {
        return [
            'cnpj' => fake()->unique()->numerify('##.###.###/####-##'),
            'razao_social' => fake()->company().' Ltda',
            'nome_fantasia' => fake()->company(),
            'categoria' => fake()->randomElement(['manutencao', 'limpeza', 'seguranca', 'jardinagem', 'entregas', 'outro']),
            'status' => 'ativo',
            'telefone' => fake()->numerify('(##) ####-####'),
            'email' => fake()->unique()->companyEmail(),
            'versao' => 1,
        ];
    }
}
