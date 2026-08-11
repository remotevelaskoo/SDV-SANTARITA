<?php

namespace Database\Factories;

use App\Models\Encomenda;
use App\Models\Imovel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Encomenda>
 */
class EncomendaFactory extends Factory
{
    protected $model = Encomenda::class;

    public function definition(): array
    {
        return [
            'protocol' => 'SRE-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'recipient_name' => fake()->name(),
            'imovel_id' => Imovel::factory(),
            'carrier' => fake()->randomElement(['Correios', 'Mercado Livre', 'Amazon']),
            'type' => 'caixa',
            'storage_location' => fake()->bothify('Prateleira ?#'),
            'status' => 'aguardando',
            'received_at' => now(),
        ];
    }
}
