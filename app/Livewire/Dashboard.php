<?php

namespace App\Livewire;

use App\Models\CaixaMovimentacao;
use App\Models\HistoricoAcesso;
use App\Models\Pessoa;
use App\Models\PreRegistration;
use App\Models\Veiculo;
use App\Models\Vinculo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;

class Dashboard extends Component
{
    public string $period = 'hoje';

    public function setPeriod(string $period): void
    {
        if (in_array($period, ['hoje', '7dias', '30dias'], true)) {
            $this->period = $period;
        }
    }

    /** @return list<array<string, mixed>> */
    private function metrics(): array
    {
        $today = now()->startOfDay();

        return [
            $this->metric('Pessoas cadastradas', Pessoa::query()->where('status', 'ativo')->count(), 'Base atual'),
            $this->metric('Visitantes hoje', HistoricoAcesso::query()->where('occurred_at', '>=', $today)->whereHas('pessoa.vinculos', fn ($query) => $query->where('tipo', 'visitante'))->distinct('pessoa_id')->count('pessoa_id'), 'Hoje, desde 00h00'),
            $this->metric('Entradas hoje', HistoricoAcesso::query()->where('occurred_at', '>=', $today)->where('tipo', 'entrada')->count(), 'Hoje, desde 00h00'),
            $this->metric('Saídas hoje', HistoricoAcesso::query()->where('occurred_at', '>=', $today)->where('tipo', 'saida')->count(), 'Hoje, desde 00h00'),
            $this->metric('Moradores', Vinculo::query()->whereIn('tipo', ['morador', 'proprietario', 'inquilino'])->whereNull('ended_at')->count(), 'Vínculos vigentes'),
            $this->metric('Prestadores', Vinculo::query()->where('tipo', 'prestador')->whereNull('ended_at')->count(), 'Vínculos vigentes'),
            $this->metric('Veículos cadastrados', Veiculo::query()->where('status', 'ativo')->count(), 'Base atual'),
            $this->metric('Arrecadação hoje', (float) CaixaMovimentacao::query()->where('occurred_at', '>=', $today)->where('type', 'entrada')->sum('amount'), 'Movimentações de hoje', 'currency'),
        ];
    }

    /** @return array<string, mixed> */
    private function metric(string $label, int|float $value, string $period, string $type = 'number'): array
    {
        return ['label' => $label, 'value' => $value, 'type' => $type, 'variation' => null, 'trend' => 'stable', 'comparison' => 'Dados reais', 'period' => $period, 'updated' => now()->format('H:i'), 'link' => false];
    }

    /** @return list<array<string, string>> */
    private function alerts(): array
    {
        $late = PreRegistration::query()->where('status', 'aguardando')->where('submitted_at', '<=', now()->subDay())->count();

        return $late > 0 ? [[
            'severity' => 'warning',
            'title' => "{$late} pré-cadastro(s) aguardando análise há mais de 24 horas",
            'description' => 'Solicitações pendentes de decisão da portaria.',
        ]] : [];
    }

    /** @return list<array<string, string>> */
    private function accesses(): array
    {
        return HistoricoAcesso::query()->with(['pessoa.documentos', 'pessoa.vinculos', 'imovel', 'veiculo'])->latest('occurred_at')->limit(6)->get()->map(fn (HistoricoAcesso $access) => [
            'time' => $access->occurred_at->format('H:i'),
            'name' => $access->pessoa?->nomeExibicao() ?? 'Não identificado',
            'document' => $this->maskDocument($access->pessoa?->documentos->first()?->valor_apresentacao),
            'relation' => ucfirst((string) ($access->pessoa?->vinculos->first()?->tipo ?? 'não identificado')),
            'property' => $access->imovel?->label() ?? 'Sem imóvel',
            'point' => $access->ponto_acesso,
            'plate' => $access->veiculo?->plate_display ?? '',
            'type' => $access->tipo,
            'result' => $access->resultado,
        ])->all();
    }

    private function maskDocument(?string $document): string
    {
        $digits = preg_replace('/\D/', '', (string) $document) ?? '';

        return strlen($digits) === 11 ? '***.***.'.substr($digits, 6, 3).'-**' : 'Documento protegido';
    }

    /** @return array<string, list<array{label: string, entries: int, exits: int}>> */
    private function series(): array
    {
        return [
            'hoje' => $this->seriesFor(now()->startOfDay(), 'H'),
            '7dias' => $this->seriesFor(now()->subDays(6)->startOfDay(), 'd/m'),
            '30dias' => $this->seriesFor(now()->subDays(29)->startOfDay(), 'd/m'),
        ];
    }

    /** @return list<array{label: string, entries: int, exits: int}> */
    private function seriesFor(Carbon $start, string $format): array
    {
        return HistoricoAcesso::query()->where('occurred_at', '>=', $start)->orderBy('occurred_at')->get()
            ->groupBy(fn (HistoricoAcesso $item) => $item->occurred_at->format($format))
            ->map(fn ($items, string $label) => ['label' => $label, 'entries' => $items->where('tipo', 'entrada')->count(), 'exits' => $items->where('tipo', 'saida')->count()])
            ->values()->all();
    }

    public function render(): View
    {
        return view('livewire.dashboard', [
            'metrics' => $this->metrics(),
            'alerts' => $this->alerts(),
            'accesses' => $this->accesses(),
            'series' => $this->series(),
        ])->layout('components.layouts.app', ['title' => 'Dashboard operacional']);
    }
}
