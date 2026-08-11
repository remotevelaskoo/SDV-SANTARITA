<?php

namespace Database\Factories;

use App\Models\Pessoa;
use App\Models\PessoaDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PessoaDocumento>
 */
class PessoaDocumentoFactory extends Factory
{
    protected $model = PessoaDocumento::class;

    public function definition(): array
    {
        $digits = fake()->unique()->numerify('###########');

        return [
            'pessoa_id' => Pessoa::factory(),
            'tipo' => 'cpf',
            'pais' => 'BR',
            'valor_normalizado' => $digits,
            'valor_apresentacao' => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits),
            'status' => 'ativo',
            'started_at' => now(),
        ];
    }
}
