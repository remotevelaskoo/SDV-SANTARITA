<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UsuarioImplantacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsuarioImplantacao>
 */
class UsuarioImplantacaoFactory extends Factory
{
    protected $model = UsuarioImplantacao::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => 'ativa',
        ];
    }
}
