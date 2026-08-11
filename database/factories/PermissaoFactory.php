<?php

namespace Database\Factories;

use App\Models\Permissao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permissao>
 */
class PermissaoFactory extends Factory
{
    protected $model = Permissao::class;

    public function definition(): array
    {
        return [
            'chave' => fake()->unique()->slug(2),
            'modulo' => 'geral',
            'descricao' => fake()->sentence(),
        ];
    }
}
