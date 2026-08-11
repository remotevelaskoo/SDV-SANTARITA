<?php

namespace Database\Factories;

use App\Models\Condominio;
use App\Models\Imovel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Imovel>
 */
class ImovelFactory extends Factory
{
    protected $model = Imovel::class;

    public function definition(): array
    {
        return [
            'condominio_id' => Condominio::factory(),
            'bloco_id' => null,
            'codigo' => strtoupper(fake()->unique()->bothify('SRA-?-###')),
            'unidade' => fake()->numerify('###'),
            'tipo' => 'apartamento',
            'status' => 'ativo',
            'versao' => 1,
        ];
    }
}
