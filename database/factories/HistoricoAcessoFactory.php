<?php

namespace Database\Factories;

use App\Models\HistoricoAcesso;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<HistoricoAcesso>
 */
class HistoricoAcessoFactory extends Factory
{
    protected $model = HistoricoAcesso::class;

    public function definition(): array
    {
        return [
            'ponto_acesso' => 'Portaria Principal',
            'tipo' => 'entrada',
            'resultado' => 'liberado',
            'protocol' => 'SRA-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
            'occurred_at' => now(),
        ];
    }
}
