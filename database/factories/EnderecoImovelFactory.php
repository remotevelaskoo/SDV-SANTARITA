<?php

namespace Database\Factories;

use App\Models\EnderecoImovel;
use App\Models\Imovel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnderecoImovel>
 */
class EnderecoImovelFactory extends Factory
{
    protected $model = EnderecoImovel::class;

    public function definition(): array
    {
        return [
            'imovel_id' => Imovel::factory(),
            'zip_code' => fake()->numerify('#####-###'),
            'address' => fake()->streetName(),
            'address_number' => fake()->buildingNumber(),
            'address_complement' => null,
            'district' => fake()->citySuffix(),
            'city' => fake()->city(),
            'state' => 'SP',
            'started_at' => now(),
            'ended_at' => null,
        ];
    }
}
