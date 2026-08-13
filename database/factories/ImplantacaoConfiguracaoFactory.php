<?php

namespace Database\Factories;

use App\Models\Configuracao;
use App\Models\ImplantacaoConfiguracao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImplantacaoConfiguracao>
 */
class ImplantacaoConfiguracaoFactory extends Factory
{
    protected $model = ImplantacaoConfiguracao::class;

    public function definition(): array
    {
        return [
            'configuracao_id' => Configuracao::factory(),
            'valor' => fake()->word(),
        ];
    }
}
