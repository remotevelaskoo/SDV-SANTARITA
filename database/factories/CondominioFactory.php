<?php

namespace Database\Factories;

use App\Models\Condominio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Condominio>
 */
class CondominioFactory extends Factory
{
    protected $model = Condominio::class;

    public function definition(): array
    {
        return [
            'nome' => 'Condomínio '.fake()->unique()->streetName(),
            'codigo' => strtoupper(fake()->unique()->bothify('COND-###')),
            'status' => 'ativo',
        ];
    }
}
