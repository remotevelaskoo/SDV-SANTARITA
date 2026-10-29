<?php

namespace Tests\Support;

use App\Models\Implantacao;
use App\Models\Perfil;
use App\Models\Permissao;
use App\Models\User;
use App\Models\UsuarioImplantacao;
use App\Models\UsuarioPerfil;
use Illuminate\Support\Str;

trait OperadorComPermissoes
{
    private function operador(string ...$chaves): User
    {
        $user = User::factory()->create();
        $perfil = Perfil::factory()->create();

        foreach ($chaves as $chave) {
            $permissao = Permissao::query()->firstOrCreate(['chave' => $chave], ['modulo' => 'teste', 'descricao' => $chave]);
            $perfil->permissoes()->attach($permissao->id, ['id' => (string) Str::uuid7(), 'implantacao_id' => Implantacao::current()->id]);
        }

        UsuarioPerfil::factory()->for($user)->for($perfil)->create();
        UsuarioImplantacao::factory()->for($user)->create(['status' => 'ativa']);

        return $user;
    }
}
