<?php

namespace App\Support;

use App\Models\Implantacao;
use App\Models\Scopes\ImplantacaoScope;
use App\Models\UsuarioImplantacao;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Resolve a implantação ativa do processo atual.
 *
 * Fora de uma requisição autenticada (artisan, seeders, testes sem
 * override, rotas públicas como o pré-cadastro), a resolução continua
 * sendo a única implantação `ativa` — resolução por subdomínio para
 * rotas públicas é a pendência ADR-002 PEN-ADR-002-004, fora de escopo.
 * Dentro de uma requisição autenticada, a implantação ativa vem da sessão
 * (gravada por App\Http\Middleware\EnsureImplantacaoSelected após o
 * usuário escolher, quando tem mais de uma) e é revalidada a cada
 * chamada — nunca confia apenas no valor guardado (ADR-002 §11.3).
 */
class ImplantacaoContext
{
    private static ?Implantacao $override = null;

    private static bool $overridden = false;

    public static function current(): Implantacao
    {
        if (self::$overridden) {
            if (self::$override === null) {
                throw new RuntimeException('Nenhuma implantação ativa no contexto de teste. Use ImplantacaoContext::setCurrentForTesting().');
            }

            return self::$override;
        }

        if (Auth::check() && session()->has('implantacao_atual_id')) {
            return self::currentFromSession();
        }

        $implantacao = Implantacao::query()->where('status', 'ativa')->first();

        if ($implantacao === null) {
            throw new RuntimeException('Nenhuma implantação ativa encontrada. Rode o seeder de fundação antes de usar entidades operacionais.');
        }

        return $implantacao;
    }

    private static function currentFromSession(): Implantacao
    {
        $implantacaoId = session('implantacao_atual_id');

        // UsuarioImplantacao usa BelongsToImplantacao (ImplantacaoScope), que
        // por sua vez chama current() — resolver o contexto a partir da
        // própria sessão exige consultar sem esse escopo, como o próprio
        // ImplantacaoScope documenta ("acesso global excepcional").
        $temAcesso = UsuarioImplantacao::withoutGlobalScope(ImplantacaoScope::class)
            ->where('user_id', Auth::id())
            ->where('implantacao_id', $implantacaoId)
            ->where('status', 'ativa')
            ->exists();

        $implantacao = $temAcesso
            ? Implantacao::query()->where('id', $implantacaoId)->where('status', 'ativa')->first()
            : null;

        if ($implantacao === null) {
            throw new RuntimeException('A implantação selecionada não está mais disponível para este usuário.');
        }

        return $implantacao;
    }

    public static function setCurrentForTesting(?Implantacao $implantacao): void
    {
        self::$overridden = true;
        self::$override = $implantacao;
    }

    public static function forgetCurrent(): void
    {
        self::$overridden = false;
        self::$override = null;
    }
}
