<?php

namespace Database\Factories;

use App\Models\CaixaMovimentacao;
use App\Models\CaixaTurno;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaixaMovimentacao>
 */
class CaixaMovimentacaoFactory extends Factory
{
    protected $model = CaixaMovimentacao::class;

    public function definition(): array
    {
        return [
            'caixa_turno_id' => CaixaTurno::factory(),
            'type' => 'entrada',
            'amount' => 15.00,
            'method' => 'dinheiro',
            'description' => fake()->sentence(3),
            'occurred_at' => now(),
        ];
    }
}
