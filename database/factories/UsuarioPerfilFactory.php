<?php

namespace Database\Factories;

use App\Models\Perfil;
use App\Models\User;
use App\Models\UsuarioPerfil;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsuarioPerfil>
 */
class UsuarioPerfilFactory extends Factory
{
    protected $model = UsuarioPerfil::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'perfil_id' => Perfil::factory(),
            'started_at' => now(),
        ];
    }
}
