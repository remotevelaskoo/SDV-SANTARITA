<?php

namespace App\Services;

use App\Models\AutorizacaoAcesso;
use App\Models\Vinculo;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Perfil da pessoa num atendimento (Turista, Visitante, Morador...) e o
 * local de destino. Regra do PO (11/11/2026): só o turista contribui, e o
 * turista não tem imóvel: o destino dele é a praia.
 *
 * - visita de 24 h (AutorizacaoAcesso turista_24h) vigente no momento:
 *   sem imóvel = Turista (local "Praia"); com imóvel = Visitante;
 * - senão, o vínculo ativo da pessoa (Proprietário, Morador, Prestador...);
 * - sem visita nem vínculo: "Sem vínculo".
 */
class PerfilAtendimento
{
    public const TURISTA = 'Turista';

    public const PRAIA = 'Praia';

    /**
     * @param  Collection<int, array{chave: string, pessoa_id: ?string, momento: CarbonInterface, imovel_id?: ?string, imovel?: ?string}>  $atendimentos
     * @return array<string, array{perfil: string, local: string, turista: bool}>
     */
    public function resolver(Collection $atendimentos): array
    {
        $pessoaIds = $atendimentos->pluck('pessoa_id')->filter()->unique()->values();

        $vinculos = $pessoaIds->isEmpty() ? collect() : Vinculo::query()->with('imovel')
            ->whereIn('pessoa_id', $pessoaIds)->whereNull('ended_at')->orderBy('started_at')
            ->get()->groupBy('pessoa_id');
        $visitas = $pessoaIds->isEmpty() ? collect() : AutorizacaoAcesso::query()->with('imovel')
            ->whereIn('pessoa_id', $pessoaIds)->where('tipo', AutorizacaoAcesso::TIPO_TURISTA)
            ->whereNotIn('status', ['cancelada'])->orderByDesc('inicio')
            ->get()->groupBy('pessoa_id');

        return $atendimentos->mapWithKeys(function (array $a) use ($vinculos, $visitas): array {
            $momento = $a['momento'];
            $imovelAtendimento = $a['imovel'] ?? null;
            // Folga de 10 min: a visita pode ser gravada logo depois do atendimento.
            $visita = ($visitas[$a['pessoa_id']] ?? collect())->first(
                fn (AutorizacaoAcesso $v) => $v->inicio->lte($momento->copy()->addMinutes(10)) && ($v->fim === null || $v->fim->gte($momento))
            );

            if ($visita !== null) {
                $turista = $visita->imovel_id === null && ($a['imovel_id'] ?? null) === null;

                return [$a['chave'] => [
                    'perfil' => $turista ? self::TURISTA : 'Visitante',
                    'local' => $turista ? self::PRAIA : ($imovelAtendimento ?? $visita->imovel?->label() ?? '—'),
                    'turista' => $turista,
                ]];
            }

            $vinculo = ($vinculos[$a['pessoa_id']] ?? collect())->first();

            return [$a['chave'] => [
                'perfil' => $vinculo ? BuscaPessoasValidacao::rotuloVinculo($vinculo->tipo) : 'Sem vínculo',
                'local' => $imovelAtendimento ?? $vinculo?->imovel?->label() ?? '—',
                'turista' => false,
            ]];
        })->all();
    }

    /** @return array{perfil: string, local: string, turista: bool} */
    public function daPessoa(?string $pessoaId, CarbonInterface $momento): array
    {
        if ($pessoaId === null) {
            return ['perfil' => 'Sem vínculo', 'local' => '—', 'turista' => false];
        }

        return $this->resolver(collect([['chave' => 'p', 'pessoa_id' => $pessoaId, 'momento' => $momento]]))['p'];
    }
}
