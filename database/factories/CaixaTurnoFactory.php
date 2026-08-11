<?php

namespace Database\Factories;

use App\Models\CaixaTurno;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaixaTurno>
 */
class CaixaTurnoFactory extends Factory
{
    protected $model = CaixaTurno::class;

    public function definition(): array
    {
        return [
            'terminal' => 'Caixa 01 — Guarita',
            'opened_at' => now(),
            'opening_balance' => 200.00,
            'status' => 'aberto',
        ];
    }
}
