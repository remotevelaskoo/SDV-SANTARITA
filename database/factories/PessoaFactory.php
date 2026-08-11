<?php

namespace Database\Factories;

use App\Models\Pessoa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pessoa>
 */
class PessoaFactory extends Factory
{
    protected $model = Pessoa::class;

    public function definition(): array
    {
        return [
            'nome' => fake()->name(),
            'nome_social' => null,
            'data_nascimento' => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
            'status' => 'ativo',
            'versao' => 1,
        ];
    }
}
