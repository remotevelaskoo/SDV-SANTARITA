<?php

namespace Database\Factories;

use App\Models\Implantacao;
use App\Models\Organizacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Implantacao>
 */
class ImplantacaoFactory extends Factory
{
    protected $model = Implantacao::class;

    public function definition(): array
    {
        return [
            'organizacao_id' => Organizacao::factory(),
            'nome' => fake()->unique()->city(),
            'slug' => fake()->unique()->slug(2),
            'status' => 'ativa',
        ];
    }
}
