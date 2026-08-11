<?php

namespace Database\Seeders;

use App\Models\Implantacao;
use App\Models\Perfil;
use App\Models\Permissao;
use App\Models\User;
use App\Models\UsuarioImplantacao;
use App\Models\UsuarioPerfil;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Semeia o catálogo mínimo de permissões/perfis e associa os dois usuários
 * demo (PortariaDemoSeeder) à implantação Santa Rita. O perfil "Portaria"
 * concede a mesma permissão já representada por
 * `users.can_edit_pre_registrations` — os dois continuam coexistindo
 * porque `PreRegistrationPolicy` ainda usa a coluna direta; reconciliar os
 * dois sistemas fica para quando houver tela de gestão de perfis (P19/P20).
 */
class UsuarioDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(FoundationSeeder::class);
        $this->call(PortariaDemoSeeder::class);

        $implantacaoId = Implantacao::query()->where('slug', 'santa-rita')->value('id');

        $permissao = Permissao::query()->firstOrCreate(
            ['chave' => 'pre-registration.edit'],
            ['modulo' => 'pre-cadastro', 'descricao' => 'Corrigir dados de um pré-cadastro antes da aprovação']
        );

        $perfil = Perfil::query()->firstOrCreate(
            ['implantacao_id' => $implantacaoId, 'nome' => 'Portaria'],
            ['status' => 'ativo']
        );

        if (! $perfil->permissoes()->where('permissoes.id', $permissao->id)->exists()) {
            $perfil->permissoes()->attach($permissao->id, ['id' => (string) Str::uuid7(), 'implantacao_id' => $implantacaoId]);
        }

        foreach (['portaria' => true, 'portaria.leitura' => false] as $username => $comPerfil) {
            $user = User::query()->where('username', $username)->first();

            if ($user === null) {
                continue;
            }

            UsuarioImplantacao::query()->firstOrCreate([
                'implantacao_id' => $implantacaoId,
                'user_id' => $user->id,
            ], ['status' => 'ativa']);

            if ($comPerfil && ! UsuarioPerfil::query()->where('user_id', $user->id)->where('perfil_id', $perfil->id)->whereNull('ended_at')->exists()) {
                UsuarioPerfil::query()->create([
                    'implantacao_id' => $implantacaoId,
                    'user_id' => $user->id,
                    'perfil_id' => $perfil->id,
                    'started_at' => now(),
                ]);
            }
        }
    }
}
