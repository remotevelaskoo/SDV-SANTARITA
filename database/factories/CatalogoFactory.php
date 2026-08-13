<?php

namespace Database\Factories;

use App\Models\Catalogo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Catalogo>
 */
class CatalogoFactory extends Factory
{
    protected $model = Catalogo::class;

    public function definition(): array
    {
        return [
            'chave' => fake()->unique()->slug(2),
            'rotulo' => fake()->words(3, true),
        ];
    }
}
