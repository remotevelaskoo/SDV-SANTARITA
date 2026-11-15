<?php

namespace App\Services\Gestao;

use App\Models\CaixaMovimentacao;
use App\Models\CaixaTurno;
use App\Models\Contribuicao;
use App\Models\HistoricoAcesso;
use App\Models\Pessoa;
use App\Models\PreRegistration;
use Illuminate\Support\Carbon;

/**
 * Números de um dia para a gestão (PO 15/11/2026): arrecadação, caixa por
 * operador, entradas e saídas, cadastros e contribuições.
 *
 * Lista de quem entrou (decisão do PO, 15/11/2026 à tarde): vai nome, tipo,
 * horário e se contribuiu. Documento (CPF/RG) e foto nunca saem do
 * condomínio.
 */
class MetricasDia
{
    /** Teto da lista do dia enviada à nuvem (o resto continua só no servidor local). */
    public const LIMITE_PESSOAS = 500;

    /** @return array<string, mixed> */
    public function calcular(Carbon $dia): array
    {
        $inicio = $dia->copy()->startOfDay();
        $fim = $dia->copy()->endOfDay();

        $contribuicoes = Contribuicao::query()->whereBetween('registrada_em', [$inicio, $fim]);
        $pagas = (clone $contribuicoes)->where('situacao_pagamento', 'pago');
        $lancamentos = CaixaMovimentacao::query()->whereBetween('occurred_at', [$inicio, $fim]);

        $porForma = [];
        foreach ((clone $pagas)->selectRaw('forma_pagamento as forma, sum(valor) as total')->groupBy('forma_pagamento')->get() as $linha) {
            $porForma[$linha->forma ?? 'outro'] = round(($porForma[$linha->forma ?? 'outro'] ?? 0) + (float) $linha->total, 2);
        }
        foreach ((clone $lancamentos)->where('type', 'entrada')->selectRaw('method as forma, sum(amount) as total')->groupBy('method')->get() as $linha) {
            $porForma[$linha->forma ?? 'outro'] = round(($porForma[$linha->forma ?? 'outro'] ?? 0) + (float) $linha->total, 2);
        }

        $valorContribuicoes = round((float) (clone $pagas)->sum('valor'), 2);
        $valorManual = round((float) (clone $lancamentos)->where('type', 'entrada')->sum('amount'), 2);
        $saidas = round((float) (clone $lancamentos)->whereIn('type', ['saida', 'estorno'])->sum('amount'), 2);

        $situacoes = (clone $contribuicoes)->selectRaw('situacao_pagamento as s, count(*) as n')->groupBy('situacao_pagamento')->pluck('n', 's');
        $acessos = HistoricoAcesso::query()->whereBetween('occurred_at', [$inicio, $fim]);

        return [
            'dia' => $inicio->toDateString(),
            'arrecadacao' => [
                'total' => round($valorContribuicoes + $valorManual, 2),
                'contribuicoes' => $valorContribuicoes,
                'lancamentos_manuais' => $valorManual,
                'saidas_e_estornos' => $saidas,
                'por_forma' => $porForma,
            ],
            'contribuicoes' => [
                'pagas' => (int) ($situacoes['pago'] ?? 0),
                'nao_contribui' => (int) ($situacoes['sem_pagamento'] ?? 0),
                'isentas' => (int) ($situacoes['isento'] ?? 0),
            ],
            'acessos' => [
                'entradas' => (clone $acessos)->where('tipo', 'entrada')->where('resultado', 'liberado')->count(),
                'saidas' => (clone $acessos)->where('tipo', 'saida')->count(),
                'negados' => (clone $acessos)->where('resultado', 'negado')->count(),
                'pendentes' => (clone $acessos)->where('resultado', 'pendente')->count(),
                'pessoas' => (clone $acessos)->whereNotNull('pessoa_id')->distinct()->count('pessoa_id'),
            ],
            'cadastros' => [
                'pessoas_novas' => Pessoa::query()->whereBetween('created_at', [$inicio, $fim])->count(),
                'pre_cadastros_recebidos' => PreRegistration::query()->whereBetween('submitted_at', [$inicio, $fim])->count(),
                'pre_cadastros_aguardando' => PreRegistration::query()->where('status', 'aguardando')->count(),
            ],
            'caixas' => $this->caixas($inicio, $fim),
            'pessoas' => $this->pessoas($inicio, $fim),
            'gerado_em' => now()->toIso8601String(),
        ];
    }

    /** Entradas do dia: nome, tipo de vínculo e pagamento. Sem documento e sem foto. @return list<array<string, mixed>> */
    private function pessoas(Carbon $inicio, Carbon $fim): array
    {
        return HistoricoAcesso::query()
            ->whereBetween('occurred_at', [$inicio, $fim])->where('tipo', 'entrada')
            ->with(['pessoa:id,nome,nome_social', 'pessoa.vinculos:id,pessoa_id,tipo,ended_at', 'contribuicao', 'operator:id,name'])
            ->latest('occurred_at')->limit(self::LIMITE_PESSOAS)->get()
            ->map(function (HistoricoAcesso $acesso): array {
                $vinculo = $acesso->pessoa?->vinculos->firstWhere('ended_at', null) ?? $acesso->pessoa?->vinculos->first();
                $contribuicao = $acesso->contribuicao;

                return [
                    'hora' => $acesso->occurred_at?->toIso8601String(),
                    'nome' => $acesso->pessoa?->nomeExibicao() ?? 'Não identificado',
                    'tipo' => $vinculo?->tipo ?? 'nao_identificado',
                    'resultado' => $acesso->resultado,
                    'pagamento' => $contribuicao?->situacao_pagamento,
                    'valor' => $contribuicao?->valor !== null ? round((float) $contribuicao->valor, 2) : null,
                    'forma' => $contribuicao?->forma_pagamento,
                    'operador' => $acesso->operator?->name,
                ];
            })->values()->all();
    }

    /** Caixas abertos no dia ou ainda abertos, com o que cada um arrecadou. @return list<array<string, mixed>> */
    private function caixas(Carbon $inicio, Carbon $fim): array
    {
        return CaixaTurno::query()->with(['operator:id,name', 'posto:id,nome'])
            ->where(fn ($q) => $q->whereBetween('opened_at', [$inicio, $fim])
                ->orWhere(fn ($q) => $q->where('opened_at', '<', $inicio)->where(fn ($q) => $q->whereNull('closed_at')->orWhere('closed_at', '>=', $inicio))))
            ->orderBy('opened_at')->get()
            ->map(fn (CaixaTurno $caixa) => [
                'operador' => $caixa->operator?->name ?? 'Operador removido',
                'posto' => $caixa->posto?->nome ?? $caixa->terminal,
                'aberto_em' => $caixa->opened_at?->toIso8601String(),
                'fechado_em' => $caixa->closed_at?->toIso8601String(),
                'situacao' => $caixa->status,
                'arrecadado' => $caixa->incomeTotal(),
                'saidas' => round($caixa->outflowTotal(), 2),
                'esperado' => $caixa->expectedBalance(),
                'informado' => $caixa->informed_amount !== null ? (float) $caixa->informed_amount : null,
                'diferenca' => $caixa->difference !== null ? (float) $caixa->difference : null,
                ...$this->recebimentosDoCaixa($caixa),
            ])->values()->all();
    }

    /** Formas de pagamento e atendimentos de um caixa, para o filtro por colaborador. @return array{por_forma: array<string, float>, contribuicoes_pagas: int} */
    private function recebimentosDoCaixa(CaixaTurno $caixa): array
    {
        $porForma = [];
        $pagas = Contribuicao::query()->where('caixa_turno_id', $caixa->id)->where('situacao_pagamento', 'pago');
        foreach ((clone $pagas)->selectRaw('forma_pagamento as forma, sum(valor) as total')->groupBy('forma_pagamento')->get() as $linha) {
            $porForma[$linha->forma ?? 'outro'] = round(($porForma[$linha->forma ?? 'outro'] ?? 0) + (float) $linha->total, 2);
        }
        foreach (CaixaMovimentacao::query()->where('caixa_turno_id', $caixa->id)->where('type', 'entrada')->selectRaw('method as forma, sum(amount) as total')->groupBy('method')->get() as $linha) {
            $porForma[$linha->forma ?? 'outro'] = round(($porForma[$linha->forma ?? 'outro'] ?? 0) + (float) $linha->total, 2);
        }

        return ['por_forma' => $porForma, 'contribuicoes_pagas' => (clone $pagas)->count()];
    }
}
