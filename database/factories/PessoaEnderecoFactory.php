<?php

namespace Database\Factories;

use App\Models\Pessoa;
use App\Models\PessoaEndereco;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PessoaEndereco>
 */
class PessoaEnderecoFactory extends Factory
{
    protected $model = PessoaEndereco::class;

    public function definition(): array
    {
        return [
            'pessoa_id' => Pessoa::factory(),
            'finalidade' => 'residencial',
            'zip_code' => fake()->numerify('#####-###'),
            'address' => fake()->streetName(),
            'address_number' => fake()->buildingNumber(),
            'address_complement' => null,
            'district' => fake()->citySuffix(),
            'city' => fake()->city(),
            'state' => 'SP',
            'started_at' => now(),
        ];
    }
}
