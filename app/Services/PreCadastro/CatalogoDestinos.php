<?php

namespace App\Services\PreCadastro;

use App\Models\Imovel;

/**
 * Lista única de imóveis de destino do pré-cadastro (formulário local e portal público).
 * Vem do cadastro de imóveis da implantação atual: só imóveis ativos, com o responsável
 * principal vigente quando houver. Nada é inventado: imóvel sem responsável não serve
 * para visitante (docs/021, revisão do PO de 08/11/2026).
 */
class CatalogoDestinos
{
    /** @var list<array{ref:string,label:string,responsible:?string}>|null */
    private ?array $itens = null;

    /** @return list<array{ref:string,label:string,responsible:?string}> */
    public function itens(): array
    {
        if ($this->itens !== null) {
            return $this->itens;
        }

        $itens = [];
        $imoveis = Imovel::query()->where('status', 'ativo')
            ->with(['bloco', 'responsabilidades' => fn ($query) => $query
                ->where('tipo', 'responsavel_principal')->whereNull('ended_at')->with('vinculo.pessoa')])
            ->get();

        foreach ($imoveis as $imovel) {
            $label = trim($imovel->label());
            if ($label === '' || isset($itens[$label])) {
                continue;
            }
            $vinculo = $imovel->responsabilidades->first()?->vinculo;
            $pessoa = $vinculo !== null && $vinculo->ativo() ? $vinculo->pessoa : null;
            $itens[$label] = ['ref' => (string) $imovel->id, 'label' => $label, 'responsible' => $pessoa?->nomeExibicao()];
        }
        ksort($itens, SORT_NATURAL);

        return $this->itens = array_values($itens);
    }

    /** @return list<string> */
    public function opcoes(string $tipoAcesso): array
    {
        return match ($tipoAcesso) {
            'visitante' => array_values(array_map(fn ($item) => $item['label'], array_filter($this->itens(), fn ($item) => $item['responsible'] !== null))),
            'prestador' => array_map(fn ($item) => $item['label'], $this->itens()),
            default => [],
        };
    }

    public function responsavel(?string $label): ?string
    {
        foreach ($this->itens() as $item) {
            if ($item['label'] === $label) {
                return $item['responsible'];
            }
        }

        return null;
    }

    /** Id do imóvel com este rótulo no catálogo atual (null se não estiver mais ativo). */
    public function imovelId(?string $label): ?string
    {
        foreach ($this->itens() as $item) {
            if ($item['label'] === $label) {
                return $item['ref'];
            }
        }

        return null;
    }

    /**
     * Versão enviada ao portal: só rótulo e responsável, sem identificadores internos.
     *
     * @return list<array{label:string,responsible:?string}>
     */
    public function paraPortal(): array
    {
        return array_map(fn ($item) => ['label' => mb_substr($item['label'], 0, 160), 'responsible' => $item['responsible'] !== null ? mb_substr($item['responsible'], 0, 120) : null], $this->itens());
    }
}
