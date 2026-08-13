<?php

namespace Database\Factories;

use App\Models\Catalogo;
use App\Models\CatalogoItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogoItem>
 */
class CatalogoItemFactory extends Factory
{
    protected $model = CatalogoItem::class;

    public function definition(): array
    {
        return [
            'catalogo_id' => Catalogo::factory(),
            'codigo' => fake()->unique()->slug(2, false),
            'rotulo' => fake()->words(3, true),
            'status' => 'ativo',
            'ordem' => 0,
        ];
    }
}
