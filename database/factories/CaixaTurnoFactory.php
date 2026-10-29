<?php

namespace Database\Factories;

use App\Models\CaixaTurno;
use App\Models\User;
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

    /** Caixa aberto do operador (AJ-008: cada operador tem o próprio). */
    public function doOperador(User $operador): static
    {
        return $this->state(['operator_id' => $operador->id]);
    }
}
