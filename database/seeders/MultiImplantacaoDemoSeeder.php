<?php

namespace Database\Seeders;

use App\Models\Catalogo;
use App\Models\CatalogoItem;
use App\Models\Implantacao;
use App\Models\Organizacao;
use App\Models\Perfil;
use App\Models\Permissao;
use App\Models\Scopes\ImplantacaoScope;
use App\Models\User;
use App\Models\UsuarioImplantacao;
use App\Models\UsuarioPerfil;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Cria uma segunda implantação de demonstração e um usuário com acesso a
 * ela e a Santa Rita, para tornar a seleção de implantação (P19) real e
 * demonstrável. Os 4 usuários demo originais (PortariaDemoSeeder) não são
 * tocados — continuam só em Santa Rita, sem ver a tela de seleção.
 *
 * Roda depois de UsuarioDemoSeeder (registrado nessa ordem em
 * DatabaseSeeder). A partir daqui existem 2 implantações `ativa`, então
 * toda consulta a um model com BelongsToImplantacao precisa ignorar o
 * ImplantacaoScope explicitamente — ele resolveria pra uma implantação
 * "atual" ambígua (fallback de CLI sem sessão), não necessariamente a que
 * este seeder está preenchendo.
 */
class MultiImplantacaoDemoSeeder extends Seeder
{
    public function run(): void
    {
        $organizacao = Organizacao::query()->firstOrCreate(
            ['nome' => 'Condomínio Jardins Ltda'],
            ['status' => 'ativa']
        );

        $implantacao = Implantacao::query()->firstOrCreate(
            ['slug' => 'jardins'],
            ['organizacao_id' => $organizacao->id, 'nome' => 'Jardins', 'status' => 'ativa']
        );

        $perfil = Perfil::withoutGlobalScope(ImplantacaoScope::class)->firstOrCreate(
            ['implantacao_id' => $implantacao->id, 'nome' => 'Administrador'],
            ['status' => 'ativo']
        );

        foreach (Permissao::query()->pluck('id') as $permissaoId) {
            $jaConcedida = $perfil->permissoes()
                ->withoutGlobalScope(ImplantacaoScope::class)
                ->where('permissoes.id', $permissaoId)
                ->exists();

            if (! $jaConcedida) {
                $perfil->permissoes()->attach($permissaoId, ['id' => (string) Str::uuid7(), 'implantacao_id' => $implantacao->id]);
            }
        }

        $user = User::query()->updateOrCreate(
            ['username' => 'administrador.multi'],
            [
                'name' => 'Renata Aquino',
                'email' => 'renata.aquino@sdv-multi.local',
                'password' => Hash::make('sdv2026'),
            ]
        );

        UsuarioImplantacao::withoutGlobalScope(ImplantacaoScope::class)->firstOrCreate(
            ['implantacao_id' => $implantacao->id, 'user_id' => $user->id],
            ['status' => 'ativa']
        );

        $this->vincularPerfil($user, $implantacao->id, $perfil->id);

        $santaRitaId = Implantacao::query()->where('slug', 'santa-rita')->value('id');
        $perfilAdminSantaRita = Perfil::withoutGlobalScope(ImplantacaoScope::class)
            ->where('implantacao_id', $santaRitaId)
            ->where('nome', 'Administrador')
            ->first();

        if ($perfilAdminSantaRita) {
            UsuarioImplantacao::withoutGlobalScope(ImplantacaoScope::class)->firstOrCreate(
                ['implantacao_id' => $santaRitaId, 'user_id' => $user->id],
                ['status' => 'ativa']
            );

            $this->vincularPerfil($user, $santaRitaId, $perfilAdminSantaRita->id);
        }

        $this->semearMotivosNegativa($implantacao->id, $santaRitaId);
    }

    /**
     * Copia os itens seedados para Santa Rita por `CatalogoSeeder` — evita
     * que Jardins fique com o select de motivos de negativa vazio na
     * Validação de entrada (P17 fatia 4).
     */
    private function semearMotivosNegativa(string $implantacaoId, ?string $santaRitaId): void
    {
        $catalogo = Catalogo::query()->where('chave', 'motivos_negativa')->first();

        if (! $catalogo || ! $santaRitaId) {
            return;
        }

        foreach (CatalogoItem::withoutGlobalScope(ImplantacaoScope::class)
            ->where('catalogo_id', $catalogo->id)
            ->where('implantacao_id', $santaRitaId)
            ->get(['codigo', 'rotulo', 'ordem']) as $item) {
            CatalogoItem::withoutGlobalScope(ImplantacaoScope::class)->firstOrCreate(
                ['implantacao_id' => $implantacaoId, 'catalogo_id' => $catalogo->id, 'codigo' => $item->codigo],
                ['rotulo' => $item->rotulo, 'status' => 'ativo', 'ordem' => $item->ordem],
            );
        }
    }

    private function vincularPerfil(User $user, string $implantacaoId, string $perfilId): void
    {
        $jaVinculado = UsuarioPerfil::withoutGlobalScope(ImplantacaoScope::class)
            ->where('user_id', $user->id)
            ->where('perfil_id', $perfilId)
            ->whereNull('ended_at')
            ->exists();

        if (! $jaVinculado) {
            UsuarioPerfil::query()->create([
                'implantacao_id' => $implantacaoId,
                'user_id' => $user->id,
                'perfil_id' => $perfilId,
                'started_at' => now(),
            ]);
        }
    }
}
