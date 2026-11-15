<?php

namespace App\Acesso\Dominio;

/**
 * Regra do PO (15/11/2026): todo envio à facial vai para todos os terminais
 * faciais ativos, com o mesmo cadastro, validade e foto. Este objeto junta o
 * resultado de cada terminal numa mensagem só para a tela.
 */
final class ResultadoEnvioFacialTodos
{
    /** @param  list<array{id: string, nome: string, resultado: ResultadoEnvioFacial}>  $itens */
    public function __construct(public array $itens, public ?ResultadoEnvioFacial $recusaGeral = null) {}

    public static function semTerminal(): self
    {
        return new self([], ResultadoEnvioFacial::recusado('sem_terminal', 'Não há terminal facial ativo e ligado a um ponto de acesso. O cadastro no SDV continua salvo.'));
    }

    public function aceito(): bool
    {
        return $this->recusaGeral === null && collect($this->itens)->contains(fn (array $i) => $i['resultado']->aceito());
    }

    /** Pares credencial/terminal com foto a acompanhar na tela. @return list<array{credencial: string, terminal: string, nome: string}> */
    public function acompanhar(): array
    {
        return collect($this->itens)
            ->filter(fn (array $i) => $i['resultado']->credencialId !== null && in_array($i['resultado']->foto['situacao'] ?? null, ['solicitado', 'ja_solicitado'], true))
            ->map(fn (array $i) => ['credencial' => $i['resultado']->credencialId, 'terminal' => $i['id'], 'nome' => $i['nome']])
            ->values()->all();
    }

    /** @return array{variant: string, title: string, message: string, avisos: list<string>, foto: array{variant: string, title: string, message: string}|null} */
    public function paraTela(): array
    {
        if ($this->recusaGeral !== null) {
            return $this->recusaGeral->paraTela();
        }
        if (count($this->itens) === 1) {
            // Um terminal só: a mensagem do terminal já traz o nome dele.
            return $this->itens[0]['resultado']->paraTela();
        }

        $telas = collect($this->itens)->map(fn (array $i) => ['nome' => $i['nome'], 'tela' => $i['resultado']->paraTela()]);
        $confirmados = $telas->where('tela.variant', 'success')->count();
        $total = $telas->count();

        $fotos = $telas->filter(fn (array $t) => $t['tela']['foto'] !== null);

        $pior = self::pior($telas->pluck('tela.variant')->all());

        return [
            'variant' => $pior,
            'title' => match (true) {
                $confirmados === $total => "Cadastro confirmado nos {$total} terminais faciais",
                $confirmados > 0 => "Cadastro confirmado em {$confirmados} de {$total} terminais faciais",
                default => $telas->firstWhere('tela.variant', $pior)['tela']['title']." nos {$total} terminais faciais",
            },
            'message' => $telas->map(fn (array $t) => "{$t['nome']}: {$t['tela']['message']}")->implode(' '),
            'avisos' => $telas->flatMap(fn (array $t) => $t['tela']['avisos'])->unique()->values()->all(),
            'foto' => $fotos->isEmpty() ? null : self::juntarFotos($fotos->map(fn (array $t) => ['nome' => $t['nome'], 'foto' => $t['tela']['foto']])->values()->all()),
        ];
    }

    /**
     * @param  list<array{nome: string, foto: array{variant: string, title: string, message: string}}>  $fotos
     * @return array{variant: string, title: string, message: string}
     */
    public static function juntarFotos(array $fotos): array
    {
        if (count($fotos) === 1) {
            return $fotos[0]['foto'];
        }
        $confirmadas = collect($fotos)->where('foto.variant', 'success')->count();
        $total = count($fotos);

        return [
            'variant' => self::pior(array_column(array_column($fotos, 'foto'), 'variant')),
            'title' => $confirmadas === $total ? "Foto confirmada nos {$total} terminais" : "Foto confirmada em {$confirmadas} de {$total} terminais",
            'message' => collect($fotos)->map(fn (array $f) => "{$f['nome']}: {$f['foto']['title']}.")->implode(' '),
        ];
    }

    /** @param  list<string>  $variantes */
    private static function pior(array $variantes): string
    {
        foreach (['danger', 'warning', 'info'] as $v) {
            if (in_array($v, $variantes, true)) {
                return $v;
            }
        }

        return 'success';
    }
}
