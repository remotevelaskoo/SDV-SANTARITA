<?php

namespace Database\Factories;

use App\Models\Veiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Veiculo>
 */
class VeiculoFactory extends Factory
{
    protected $model = Veiculo::class;

    public function definition(): array
    {
        $plate = strtoupper(fake()->unique()->bothify('???#?##'));

        return [
            'plate_display' => $plate,
            'plate_normalized' => Veiculo::normalizePlate($plate),
            'country' => 'BR',
            'type' => 'carro',
            'brand' => fake()->randomElement(['Toyota', 'Honda', 'Volkswagen', 'Fiat']),
            'model' => fake()->word(),
            'color' => fake()->safeColorName(),
            'status' => 'ativo',
        ];
    }
}
