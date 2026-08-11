<?php

namespace Database\Factories;

use App\Models\Bloco;
use App\Models\Condominio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bloco>
 */
class BlocoFactory extends Factory
{
    protected $model = Bloco::class;

    public function definition(): array
    {
        return [
            'condominio_id' => Condominio::factory(),
            'nome' => 'Bloco '.strtoupper(fake()->randomLetter()),
            'codigo' => null,
            'ordem' => fake()->numberBetween(1, 10),
            'status' => 'ativo',
        ];
    }
}
