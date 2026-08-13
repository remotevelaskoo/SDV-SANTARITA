<?php

namespace Database\Factories;

use App\Models\Configuracao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Configuracao>
 */
class ConfiguracaoFactory extends Factory
{
    protected $model = Configuracao::class;

    public function definition(): array
    {
        return [
            'chave' => fake()->unique()->slug(2),
            'categoria' => 'dados gerais',
            'tipo' => 'texto',
            'rotulo' => fake()->words(3, true),
            'descricao' => fake()->sentence(),
            'valor_padrao' => null,
        ];
    }
}
