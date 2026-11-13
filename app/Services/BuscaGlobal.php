<?php

namespace App\Services;

use App\Models\HistoricoAcesso;
use App\Models\Imovel;
use App\Models\PreRegistration;
use App\Models\User;
use App\Models\Veiculo;
use App\Support\NormalizadorBusca;

/**
 * Busca global do cabeçalho (docs/004 §7.1, escopo aprovado pelo PO em 13/11/2026):
 * pessoas, veículos (placa), imóveis, protocolos de atendimento e pré-cadastros.
 *
 * - cada grupo só existe para quem tem a permissão do módulo de destino;
 * - no máximo LIMITE itens por grupo, com "ver todos" no módulo;
 * - pessoas usam a mesma busca da Validação (CPF e telefone só por valor
 *   completo, documento mascarado, sem foto);
 * - tudo dentro da implantação ativa (escopo global dos modelos).
 */
class BuscaGlobal
{
    public const LIMITE = 5;

    public const TAMANHO_MINIMO = 2;

    public function __construct(private BuscaPessoasValidacao $pessoas) {}

    /**
     * @return list<array{chave: string, titulo: string, itens: list<array{titulo: string, detalhe: string, url: string}>, mais: bool, ver_todos: ?string}>
     */
    public function buscar(string $termo, User $usuario): array
    {
        $termo = trim(mb_substr($termo, 0, 80));
        if (mb_strlen($termo) < self::TAMANHO_MINIMO) {
            return [];
        }

        $grupos = [];

        if ($usuario->hasPermission('pessoas.gerenciar')) {
            $achadas = $this->pessoas->buscar($termo, podeVerFoto: false, limite: self::LIMITE);
            $grupos[] = $this->grupo('pessoas', 'Pessoas', array_map(fn (array $p) => [
                'titulo' => $p['nome'],
                'detalhe' => collect([$p['documento'] !== '' ? 'CPF '.$p['documento'] : null, $p['tipo'], $p['situacao']])->filter()->join(' · '),
                'url' => route('people', ['pessoa' => $p['id']]),
            ], $achadas['resultados']), $achadas['mais'], route('people', ['q' => $termo]));
        }

        $placa = NormalizadorBusca::documento($termo);
        if ($usuario->hasPermission('veiculos.consultar') && strlen($placa) >= 3 && preg_match('/^[A-Z0-9]+$/', $placa)) {
            $veiculos = Veiculo::query()->where('plate_normalized', 'like', '%'.$placa.'%')->orderBy('plate_normalized')->limit(self::LIMITE + 1)->get();
            $grupos[] = $this->grupo('veiculos', 'Veículos', $veiculos->take(self::LIMITE)->map(fn (Veiculo $v) => [
                'titulo' => $v->plate_display,
                'detalhe' => collect([$v->brand, $v->model, $v->color])->filter()->join(' · ') ?: 'Sem marca, modelo e cor',
                'url' => route('vehicles', ['q' => $v->plate_normalized]),
            ])->all(), $veiculos->count() > self::LIMITE, route('vehicles', ['q' => $placa]));
        }

        if ($usuario->hasPermission('imoveis.consultar')) {
            $busca = mb_strtoupper($termo);
            $imoveis = Imovel::query()
                ->where(fn ($q) => $q->whereRaw('upper(codigo) like ?', ['%'.$busca.'%'])->orWhereRaw('upper(unidade) like ?', ['%'.$busca.'%']))
                ->orderBy('codigo')->limit(self::LIMITE + 1)->get();
            $grupos[] = $this->grupo('imoveis', 'Imóveis', $imoveis->take(self::LIMITE)->map(fn (Imovel $i) => [
                'titulo' => $i->codigo,
                'detalhe' => collect([$i->unidade, $i->tipo ? ucfirst((string) $i->tipo) : null, $i->status])->filter()->join(' · '),
                'url' => route('properties', ['q' => $i->codigo]),
            ])->all(), $imoveis->count() > self::LIMITE, route('properties', ['q' => $termo]));
        }

        $protocolo = mb_strtoupper($termo);
        $pareceProtocolo = strlen($protocolo) >= 4 && preg_match('/^[A-Z0-9-]+$/', $protocolo) && preg_match('/\d/', $protocolo);
        if ($usuario->hasPermission('validacao.registrar') && $pareceProtocolo) {
            $atendimentos = HistoricoAcesso::query()->with('pessoa')->where('protocol', 'like', '%'.$protocolo.'%')->latest('occurred_at')->limit(self::LIMITE + 1)->get();
            $grupos[] = $this->grupo('atendimentos', 'Protocolos de atendimento', $atendimentos->take(self::LIMITE)->map(fn (HistoricoAcesso $h) => [
                'titulo' => $h->protocol,
                'detalhe' => collect([$h->pessoa?->nomeExibicao(), self::rotuloResultado($h->resultado), $h->occurred_at?->format('d/m/Y H:i')])->filter()->join(' · '),
                'url' => route('access-history', ['q' => $h->protocol]),
            ])->all(), $atendimentos->count() > self::LIMITE, route('access-history', ['q' => $protocolo]));
        }

        if ($usuario->hasPermission('pre-registro.analisar')) {
            $texto = NormalizadorBusca::texto($termo);
            $preCadastros = PreRegistration::query()
                ->where(function ($q) use ($pareceProtocolo, $protocolo, $termo, $texto) {
                    if ($pareceProtocolo) {
                        $q->orWhere('protocol', 'like', '%'.$protocolo.'%');
                    }
                    if (preg_match('/\p{L}/u', $termo) && mb_strlen($texto) >= self::TAMANHO_MINIMO) {
                        $q->orWhere('name', 'like', '%'.trim($termo).'%');
                    }
                })
                ->when(! $pareceProtocolo && ! preg_match('/\p{L}/u', $termo), fn ($q) => $q->whereRaw('1 = 0'))
                ->latest()->limit(self::LIMITE + 1)->get();
            $grupos[] = $this->grupo('pre_cadastros', 'Pré-cadastros', $preCadastros->take(self::LIMITE)->map(fn (PreRegistration $p) => [
                'titulo' => (string) $p->name,
                'detalhe' => collect([$p->protocol, ucfirst((string) $p->access_type), str_replace('_', ' ', (string) $p->status)])->filter()->join(' · '),
                'url' => route('pre-registrations.review', $p->id),
            ])->all(), $preCadastros->count() > self::LIMITE, route('pre-registrations', ['q' => $termo]));
        }

        return array_values(array_filter($grupos, fn (array $g) => $g['itens'] !== []));
    }

    /** @param list<array{titulo: string, detalhe: string, url: string}> $itens */
    private function grupo(string $chave, string $titulo, array $itens, bool $mais, ?string $verTodos): array
    {
        return ['chave' => $chave, 'titulo' => $titulo, 'itens' => array_values($itens), 'mais' => $mais, 'ver_todos' => $verTodos];
    }

    private static function rotuloResultado(?string $resultado): ?string
    {
        return match ($resultado) {
            'liberado' => 'Liberado',
            'negado' => 'Negado',
            'pendente' => 'Pendente',
            null => null,
            default => ucfirst(str_replace('_', ' ', $resultado)),
        };
    }
}
