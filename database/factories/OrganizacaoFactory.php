<?php

namespace Database\Factories;

use App\Models\Organizacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organizacao>
 */
class OrganizacaoFactory extends Factory
{
    protected $model = Organizacao::class;

    public function definition(): array
    {
        return [
            'nome' => fake()->unique()->company(),
            'status' => 'ativa',
        ];
    }
}
