<?php

namespace App\Services;

use App\Models\Arquivo;
use App\Models\HistoricoAcesso;
use App\Models\Imovel;
use App\Models\Pessoa;
use App\Models\PessoaContato;
use App\Models\PessoaDocumento;
use App\Models\PreRegistration;
use App\Models\Vinculo;
use App\Support\NormalizadorBusca;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Busca de pessoas na Validação de Entrada (AJ-006, fatia 1, docs/020 §2).
 *
 * - roda no servidor e sempre dentro da implantação ativa (escopo global dos modelos);
 * - aceita nome parcial (sem acento), CPF/documento, telefone, protocolo e
 *   código do imóvel, combinando os critérios que o termo permitir;
 * - devolve no máximo `$limite` pessoas e indica quando há mais;
 * - nunca escolhe uma pessoa sozinha: quem seleciona é o operador.
 *
 * Documento e telefone só casam por valor completo (ou final do telefone
 * com 8 dígitos ou mais), para a busca não virar enumeração de documentos.
 */
class BuscaPessoasValidacao
{
    public const LIMITE_PADRAO = 8;

    public const TAMANHO_MINIMO = 2;

    public function __construct(private FotoPessoaResolver $fotos) {}

    /**
     * @return array{resultados: list<array<string, mixed>>, mais: bool, criterios: list<string>}
     */
    public function buscar(string $termo, bool $podeVerFoto, int $limite = self::LIMITE_PADRAO): array
    {
        $termo = trim(mb_substr($termo, 0, 80));
        if (mb_strlen($termo) < self::TAMANHO_MINIMO) {
            return ['resultados' => [], 'mais' => false, 'criterios' => []];
        }

        /** @var array<string, list<string>> $motivos pessoa_id => critérios */
        $motivos = [];
        $marcar = function (iterable $ids, string $criterio) use (&$motivos): void {
            foreach ($ids as $id) {
                if ($id !== null && ! in_array($criterio, $motivos[$id] ?? [], true)) {
                    $motivos[$id][] = $criterio;
                }
            }
        };

        $digitos = NormalizadorBusca::digitos($termo);
        $documento = NormalizadorBusca::documento($termo);
        $texto = NormalizadorBusca::texto($termo);
        $janela = $limite * 3;

        if (strlen($digitos) === 11 || (strlen($documento) >= 5 && preg_match('/\d/', $documento))) {
            $marcar(PessoaDocumento::query()
                ->whereIn('valor_normalizado', array_values(array_unique(array_filter([$digitos, $documento]))))
                ->whereNull('ended_at')
                ->limit($janela)
                ->pluck('pessoa_id'), 'documento');
        }

        if (strlen($digitos) >= 8) {
            $marcar(PessoaContato::query()
                ->whereIn('tipo', ['telefone', 'celular', 'whatsapp'])
                ->where(fn (Builder $q) => $q->where('valor_busca', $digitos)->orWhere('valor_busca', 'like', '%'.$digitos))
                ->whereNull('ended_at')
                ->limit($janela)
                ->pluck('pessoa_id'), 'telefone');
        }

        if (preg_match('/^[A-Za-z]{2,5}-[A-Za-z0-9-]{4,}$/', $termo)) {
            $protocolo = strtoupper($termo);

            $marcar(HistoricoAcesso::query()->where('protocol', $protocolo)->pluck('pessoa_id'), 'protocolo');

            $documentoPreCadastro = PreRegistration::query()->where('protocol', $protocolo)->value('document');
            if ($documentoPreCadastro !== null) {
                $marcar(PessoaDocumento::query()
                    ->whereIn('valor_normalizado', array_values(array_unique(array_filter([
                        NormalizadorBusca::digitos($documentoPreCadastro),
                        NormalizadorBusca::documento($documentoPreCadastro),
                    ]))))
                    ->whereNull('ended_at')
                    ->pluck('pessoa_id'), 'protocolo');
            }
        }

        $imovelIds = Imovel::query()->whereRaw('upper(codigo) = ?', [strtoupper($termo)])->pluck('id');
        if ($imovelIds->isNotEmpty()) {
            $marcar(Vinculo::query()
                ->whereIn('imovel_id', $imovelIds)
                ->whereNull('ended_at')
                ->limit($janela)
                ->pluck('pessoa_id'), 'imovel');
        }

        if (preg_match('/\p{L}/u', $termo) && mb_strlen($texto) >= self::TAMANHO_MINIMO) {
            $consulta = Pessoa::query();
            foreach (array_slice(explode(' ', $texto), 0, 5) as $palavra) {
                $consulta->where('nome_busca', 'like', '%'.$palavra.'%');
            }
            $marcar($consulta->orderBy('nome_busca')->limit($janela)->pluck('id'), 'nome');
        }

        if ($motivos === []) {
            return ['resultados' => [], 'mais' => false, 'criterios' => []];
        }

        $pessoas = Pessoa::query()
            ->whereIn('id', array_keys($motivos))
            ->with([
                'documentos' => fn ($q) => $q->whereNull('ended_at'),
                'vinculos' => fn ($q) => $q->whereNull('ended_at')->with('imovel'),
            ])
            ->orderBy('nome')
            ->limit($limite + 1)
            ->get();

        $mais = $pessoas->count() > $limite;
        $pessoas = $pessoas->take($limite);
        $fotos = $podeVerFoto ? $this->fotos->paraPessoas($pessoas) : [];

        return [
            'resultados' => $pessoas->map(fn (Pessoa $pessoa) => $this->resumo($pessoa, $motivos[$pessoa->id] ?? [], $fotos[$pessoa->id] ?? null, $podeVerFoto))->values()->all(),
            'mais' => $mais,
            'criterios' => array_values(array_unique(array_merge(...array_values($motivos)))),
        ];
    }

    /**
     * @param  list<string>  $motivos
     * @param  array{arquivo: Arquivo, origem: string}|null  $foto
     * @return array<string, mixed>
     */
    private function resumo(Pessoa $pessoa, array $motivos, ?array $foto, bool $podeVerFoto): array
    {
        /** @var Collection<int, Vinculo> $vinculos */
        $vinculos = $pessoa->vinculos;
        $vinculo = $vinculos->first();
        $documento = $pessoa->documentos->first()?->valor_apresentacao;
        [$situacao, $tom] = $this->situacao($pessoa, $vinculo);

        return [
            'id' => $pessoa->id,
            'nome' => $pessoa->nomeExibicao(),
            'iniciais' => self::iniciais($pessoa->nome),
            'documento' => NormalizadorBusca::mascararDocumento($documento),
            'tipo' => self::rotuloVinculo($vinculo?->tipo),
            'destino' => $vinculo?->imovel?->label() ?? 'Sem imóvel vinculado',
            'vigencia' => $vinculo ? self::vigencia($vinculo->started_at, $vinculo->ended_at) : 'Sem vínculo vigente',
            'situacao' => $situacao,
            'situacao_tom' => $tom,
            'foto_url' => $foto ? route('protected-files.show', ['arquivo' => $foto['arquivo'], 'contexto' => 'busca_validacao']) : null,
            'foto_estado' => $foto ? 'disponivel' : ($podeVerFoto ? 'sem_foto' : 'sem_permissao'),
            'motivos' => array_map(fn (string $motivo) => self::rotuloCriterio($motivo), $motivos),
            'vinculos_extras' => max(0, $vinculos->count() - 1),
        ];
    }

    /** @return array{0: string, 1: string} */
    private function situacao(Pessoa $pessoa, ?Vinculo $vinculo): array
    {
        return match (true) {
            $pessoa->status === 'bloqueado' => ['Bloqueado', 'danger'],
            $pessoa->status !== 'ativo' => ['Cadastro '.str_replace('_', ' ', (string) $pessoa->status), 'warning'],
            $vinculo === null => ['Sem vínculo ativo', 'warning'],
            $vinculo->status !== 'ativo' => ['Vínculo '.str_replace('_', ' ', (string) $vinculo->status), 'warning'],
            default => ['Cadastro ativo', 'success'],
        };
    }

    public static function vigencia(?\DateTimeInterface $inicio, ?\DateTimeInterface $fim): string
    {
        $inicioTexto = $inicio?->format('d/m/Y') ?? 'sem início';

        return $fim === null ? "Desde {$inicioTexto}, sem término" : "{$inicioTexto} a {$fim->format('d/m/Y H:i')}";
    }

    public static function rotuloVinculo(?string $tipo): string
    {
        return match ($tipo) {
            'proprietario' => 'Proprietário',
            'inquilino' => 'Inquilino',
            'morador' => 'Morador',
            'prestador' => 'Prestador',
            'visitante' => 'Visitante',
            'turista' => 'Turista',
            null => 'Sem vínculo',
            default => 'Outro acesso',
        };
    }

    public static function rotuloCriterio(string $criterio): string
    {
        return match ($criterio) {
            'documento' => 'Documento',
            'telefone' => 'Telefone',
            'protocolo' => 'Protocolo',
            'imovel' => 'Imóvel',
            default => 'Nome',
        };
    }

    public static function iniciais(string $nome): string
    {
        return collect(explode(' ', trim($nome)))
            ->filter(fn (string $parte): bool => $parte !== '' && ! in_array(mb_strtolower($parte), ['da', 'de', 'do', 'das', 'dos', 'e'], true))
            ->map(fn (string $parte): string => mb_strtoupper(mb_substr($parte, 0, 1)))
            ->take(2)
            ->join('');
    }
}
