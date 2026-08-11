<?php

namespace Database\Factories;

use App\Models\Pessoa;
use App\Models\PessoaContato;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PessoaContato>
 */
class PessoaContatoFactory extends Factory
{
    protected $model = PessoaContato::class;

    public function definition(): array
    {
        return [
            'pessoa_id' => Pessoa::factory(),
            'tipo' => 'telefone',
            'valor' => fake()->numerify('(##) 9####-####'),
            'principal' => true,
            'verificado' => false,
            'started_at' => now(),
        ];
    }

    public function email(): static
    {
        return $this->state(fn (array $attributes): array => [
            'tipo' => 'email',
            'valor' => fake()->unique()->safeEmail(),
        ]);
    }
}
