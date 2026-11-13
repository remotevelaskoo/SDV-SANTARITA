<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConferenciaDadosIdentificacao;
use App\Models\AutorizacaoAcesso;
use App\Models\HistoricoAcesso;
use App\Models\Pessoa;
use App\Models\PessoaArquivo;
use App\Models\PessoaContato;
use App\Models\PessoaDocumento;
use App\Models\Veiculo;
use App\Models\VeiculoVinculo;
use App\Models\Vinculo;
use App\Services\AuditService;
use App\Services\BuscaPessoasValidacao;
use App\Services\FotoPessoaResolver;
use App\Services\PerfilAtendimento;
use App\Support\NormalizadorBusca;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Consulta de pessoas (pedido do PO de 13/11/2026): lista com busca por
 * nome, CPF, telefone, placa ou código do imóvel; filtros de tipo, situação,
 * autorização vigente e sem foto; ficha com dados, foto, vínculos,
 * autorizações, veículos e últimos acessos; correção dos dados pessoais.
 *
 * Tudo dentro da implantação ativa (escopo global dos modelos). A correção
 * reaproveita a mesma regra da Validação de entrada: documento e contato
 * anteriores são encerrados, não apagados.
 */
class PessoaConsulta extends Component
{
    use ConferenciaDadosIdentificacao, WithPagination;

    public const POR_PAGINA = 20;

    public const TIPOS = [
        'proprietario' => 'Proprietário',
        'inquilino' => 'Inquilino',
        'morador' => 'Morador',
        'prestador' => 'Prestador',
        'visitante' => 'Visitante',
        'turista' => 'Turista',
        'sem_vinculo' => 'Sem vínculo',
    ];

    #[Url(as: 'q', except: '')]
    public string $busca = '';

    #[Url(as: 'tipo', except: '')]
    public string $filtroTipo = '';

    #[Url(as: 'situacao', except: '')]
    public string $filtroSituacao = '';

    #[Url(as: 'vigente', except: false)]
    public bool $soVigentes = false;

    #[Url(as: 'sem-foto', except: false)]
    public bool $soSemFoto = false;

    /** Pessoa aberta na ficha (usado também pela edição de dados). */
    #[Url(as: 'pessoa', except: null)]
    public ?string $currentPessoaId = null;

    public function updated(string $propriedade): void
    {
        if (in_array($propriedade, ['busca', 'filtroTipo', 'filtroSituacao', 'soVigentes', 'soSemFoto'], true)) {
            $this->resetPage();
        }
    }

    public function limparFiltros(): void
    {
        $this->reset(['busca', 'filtroTipo', 'filtroSituacao', 'soVigentes', 'soSemFoto']);
        $this->resetPage();
    }

    public function abrir(string $pessoaId, AuditService $audit): void
    {
        $pessoa = Pessoa::query()->find($pessoaId);
        abort_if($pessoa === null, 404);

        $this->cancelarEdicaoIdentificacao();
        $this->dadosIdentificacaoSalvos = false;
        $this->currentPessoaId = $pessoa->id;
        unset($this->ficha, $this->dadosIdentificacao);

        $audit->record(
            action: 'consultou_pessoa',
            module: 'pessoas',
            entityType: 'pessoas',
            entityId: $pessoa->id,
            metadata: ['origem' => 'consulta_pessoas'],
        );
    }

    public function voltarParaLista(): void
    {
        $this->cancelarEdicaoIdentificacao();
        $this->dadosIdentificacaoSalvos = false;
        $this->currentPessoaId = null;
    }

    /** Na consulta, corrigir dados exige só a permissão de gerenciar pessoas. */
    public function podeEditarIdentificacao(): bool
    {
        return $this->currentPessoaId !== null && (bool) Auth::user()?->hasPermission('pessoas.gerenciar');
    }

    #[Computed]
    public function pessoas()
    {
        $agora = now();
        $consulta = Pessoa::query()
            ->with([
                'documentos' => fn ($q) => $q->where('tipo', 'cpf')->whereNull('ended_at'),
                'vinculos' => fn ($q) => $q->whereNull('ended_at')->with('imovel')->orderBy('started_at'),
            ]);

        $this->aplicarBusca($consulta);

        if ($this->filtroSituacao !== '') {
            $consulta->where('status', $this->filtroSituacao);
        }

        if ($this->filtroTipo === 'sem_vinculo') {
            $consulta->whereDoesntHave('vinculos', fn (Builder $q) => $q->whereNull('ended_at'))
                ->whereNotIn('id', $this->autorizacoesVigentes()->select('pessoa_id'));
        } elseif ($this->filtroTipo === 'turista') {
            $consulta->whereIn('id', $this->autorizacoesVigentes()->where('tipo', AutorizacaoAcesso::TIPO_TURISTA)->whereNull('imovel_id')->select('pessoa_id'));
        } elseif ($this->filtroTipo === 'visitante') {
            $consulta->where(fn (Builder $q) => $q
                ->whereHas('vinculos', fn (Builder $v) => $v->whereNull('ended_at')->where('tipo', 'visitante'))
                ->orWhereIn('id', $this->autorizacoesVigentes()->where(fn (Builder $a) => $a->where('tipo', 'visitante')->orWhere(fn (Builder $t) => $t->where('tipo', AutorizacaoAcesso::TIPO_TURISTA)->whereNotNull('imovel_id')))->select('pessoa_id')));
        } elseif ($this->filtroTipo === 'prestador') {
            $consulta->where(fn (Builder $q) => $q
                ->whereHas('vinculos', fn (Builder $v) => $v->whereNull('ended_at')->where('tipo', 'prestador'))
                ->orWhereIn('id', $this->autorizacoesVigentes()->where('tipo', 'prestador')->select('pessoa_id')));
        } elseif (isset(self::TIPOS[$this->filtroTipo])) {
            $consulta->whereHas('vinculos', fn (Builder $v) => $v->whereNull('ended_at')->where('tipo', $this->filtroTipo));
        }

        if ($this->soVigentes) {
            $consulta->where(fn (Builder $q) => $q
                ->whereHas('vinculos', fn (Builder $v) => $v->where('status', 'ativo')->where(fn (Builder $f) => $f->whereNull('ended_at')->orWhere('ended_at', '>', $agora)))
                ->orWhereIn('id', $this->autorizacoesVigentes()->select('pessoa_id')));
        }

        if ($this->soSemFoto) {
            $consulta->whereNotIn('id', PessoaArquivo::query()->where('category', 'foto')->where('is_current', true)->select('pessoa_id'));
        }

        $pagina = $consulta->orderBy('nome')->paginate(self::POR_PAGINA);

        $podeVerFoto = $this->podeVerFoto();
        $fotos = $podeVerFoto ? app(FotoPessoaResolver::class)->paraPessoas($pagina->getCollection()) : [];
        $perfis = app(PerfilAtendimento::class)->resolver($pagina->getCollection()->map(fn (Pessoa $p) => [
            'chave' => $p->id, 'pessoa_id' => $p->id, 'momento' => now(),
        ]));

        $pagina->setCollection($pagina->getCollection()->map(function (Pessoa $pessoa) use ($fotos, $perfis, $podeVerFoto) {
            $vinculo = $pessoa->vinculos->first();
            $foto = $fotos[$pessoa->id] ?? null;
            [$situacao, $tom] = $this->situacao($pessoa->status);

            return [
                'id' => $pessoa->id,
                'nome' => $pessoa->nomeExibicao(),
                'iniciais' => BuscaPessoasValidacao::iniciais($pessoa->nome),
                'cpf' => NormalizadorBusca::mascararDocumento($pessoa->documentos->first()?->valor_apresentacao),
                'tipo' => $perfis[$pessoa->id]['perfil'] ?? 'Sem vínculo',
                'imovel' => $vinculo?->imovel?->label() ?? ($perfis[$pessoa->id]['local'] ?? '—'),
                'situacao' => $situacao,
                'situacao_tom' => $tom,
                'foto_url' => $foto ? route('protected-files.show', ['arquivo' => $foto['arquivo'], 'contexto' => 'consulta_pessoas']) : null,
                'foto_estado' => $foto ? 'disponivel' : ($podeVerFoto ? 'sem_foto' : 'sem_permissao'),
            ];
        }));

        return $pagina;
    }

    /** @return array{total: int, ativos: int, vigentes: int, sem_foto: int} */
    #[Computed]
    public function contagens(): array
    {
        $agora = now();

        return [
            'total' => Pessoa::query()->count(),
            'ativos' => Pessoa::query()->where('status', 'ativo')->count(),
            'vigentes' => Pessoa::query()->where(fn (Builder $q) => $q
                ->whereHas('vinculos', fn (Builder $v) => $v->where('status', 'ativo')->where(fn (Builder $f) => $f->whereNull('ended_at')->orWhere('ended_at', '>', $agora)))
                ->orWhereIn('id', $this->autorizacoesVigentes()->select('pessoa_id')))->count(),
            'sem_foto' => Pessoa::query()->whereNotIn('id', PessoaArquivo::query()->where('category', 'foto')->where('is_current', true)->select('pessoa_id'))->count(),
        ];
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function ficha(): ?array
    {
        $pessoa = $this->currentPessoaId ? Pessoa::query()->find($this->currentPessoaId) : null;
        if ($pessoa === null) {
            return null;
        }

        $podeVerFoto = $this->podeVerFoto();
        $foto = $podeVerFoto ? app(FotoPessoaResolver::class)->paraPessoa($pessoa) : null;
        $perfil = app(PerfilAtendimento::class)->daPessoa($pessoa->id, now());
        [$situacao, $tom] = $this->situacao($pessoa->status);

        $vinculos = Vinculo::query()->where('pessoa_id', $pessoa->id)->with('imovel')->orderByDesc('started_at')->limit(20)->get()
            ->map(fn (Vinculo $v) => [
                'tipo' => BuscaPessoasValidacao::rotuloVinculo($v->tipo),
                'imovel' => $v->imovel?->label() ?? 'Sem imóvel',
                'vigencia' => BuscaPessoasValidacao::vigencia($v->started_at, $v->ended_at),
                'ativo' => $v->status === 'ativo' && ($v->ended_at === null || $v->ended_at->isFuture()),
                'status' => str_replace('_', ' ', (string) $v->status),
            ])->all();

        $autorizacoes = AutorizacaoAcesso::query()->where('pessoa_id', $pessoa->id)->with('imovel')->orderByDesc('inicio')->limit(10)->get()
            ->map(fn (AutorizacaoAcesso $a) => [
                'tipo' => match (true) {
                    $a->tipo === AutorizacaoAcesso::TIPO_VINCULO => 'Pelo vínculo',
                    $a->tipo === AutorizacaoAcesso::TIPO_TURISTA => $a->imovel_id ? 'Visitante (24 h)' : 'Turista (24 h)',
                    default => ucfirst($a->tipo),
                },
                'periodo' => $a->inicio->format('d/m/Y H:i').' até '.($a->fim?->format('d/m/Y H:i') ?? 'sem término'),
                'imovel' => $a->imovel?->label(),
                'responsavel' => $a->responsavel,
                'vigente' => $a->vigente(),
                'status' => $a->vigente() ? 'Vigente' : ucfirst((string) $a->status),
            ])->all();

        $veiculos = VeiculoVinculo::query()->where('pessoa_id', $pessoa->id)->whereNull('ended_at')->with('veiculo')->get()
            ->filter(fn (VeiculoVinculo $vv) => $vv->veiculo !== null)
            ->map(fn (VeiculoVinculo $vv) => [
                'placa' => $vv->veiculo->plate_display,
                'descricao' => collect([$vv->veiculo->brand, $vv->veiculo->model, $vv->veiculo->color])->filter()->join(' · ') ?: 'Sem descrição',
            ])->values()->all();

        $acessos = HistoricoAcesso::query()->where('pessoa_id', $pessoa->id)->orderByDesc('occurred_at')->limit(10)->get()
            ->map(fn (HistoricoAcesso $h) => [
                'quando' => $h->occurred_at?->format('d/m/Y H:i') ?? '—',
                'resultado' => ucfirst((string) $h->resultado),
                'tom' => match ($h->resultado) { 'liberado' => 'success', 'negado' => 'danger', default => 'warning' },
                'protocolo' => $h->protocol,
            ])->all();

        return [
            'id' => $pessoa->id,
            'nome' => $pessoa->nomeExibicao(),
            'iniciais' => BuscaPessoasValidacao::iniciais($pessoa->nome),
            'situacao' => $situacao,
            'situacao_tom' => $tom,
            'tipo' => $perfil['perfil'],
            'foto_url' => $foto ? route('protected-files.show', ['arquivo' => $foto['arquivo'], 'contexto' => 'consulta_pessoas']) : null,
            'foto_origem' => $foto ? ($foto['origem'] === 'cadastro' ? 'Foto do cadastro' : 'Selfie do pré-cadastro aprovado') : null,
            'foto_estado' => $foto ? 'disponivel' : ($podeVerFoto ? 'sem_foto' : 'sem_permissao'),
            'vinculos' => $vinculos,
            'autorizacoes' => $autorizacoes,
            'veiculos' => $veiculos,
            'acessos' => $acessos,
            'cadastrado_em' => $pessoa->created_at?->format('d/m/Y'),
        ];
    }

    /**
     * Nome, CPF completo, telefone (a partir de 8 dígitos, pelo final), placa
     * ou código do imóvel. Como na Validação: documento só casa por valor
     * completo, para a busca não virar enumeração de CPFs.
     */
    private function aplicarBusca(Builder $consulta): void
    {
        $termo = trim(mb_substr($this->busca, 0, 80));
        if (mb_strlen($termo) < BuscaPessoasValidacao::TAMANHO_MINIMO) {
            return;
        }

        $digitos = NormalizadorBusca::digitos($termo);
        $documento = NormalizadorBusca::documento($termo);
        $texto = NormalizadorBusca::texto($termo);
        $placa = Veiculo::normalizePlate($termo);

        $consulta->where(function (Builder $q) use ($termo, $digitos, $documento, $texto, $placa): void {
            $q->whereRaw('1 = 0');

            if (preg_match('/\p{L}/u', $termo) && mb_strlen($texto) >= BuscaPessoasValidacao::TAMANHO_MINIMO) {
                $q->orWhere(function (Builder $nome) use ($texto): void {
                    foreach (array_slice(explode(' ', $texto), 0, 5) as $palavra) {
                        $nome->where('nome_busca', 'like', '%'.$palavra.'%');
                    }
                });
            }

            $valoresDocumento = array_values(array_unique(array_filter([
                strlen($digitos) === 11 ? $digitos : null,
                strlen($documento) >= 5 && preg_match('/\d/', $documento) ? $documento : null,
            ])));
            if ($valoresDocumento !== []) {
                $q->orWhereIn('id', PessoaDocumento::query()->whereIn('valor_normalizado', $valoresDocumento)->whereNull('ended_at')->select('pessoa_id'));
            }

            if (strlen($digitos) >= 8) {
                $q->orWhereIn('id', PessoaContato::query()
                    ->whereIn('tipo', ['telefone', 'celular', 'whatsapp'])
                    ->where(fn (Builder $c) => $c->where('valor_busca', $digitos)->orWhere('valor_busca', 'like', '%'.$digitos))
                    ->whereNull('ended_at')->select('pessoa_id'));
            }

            if (preg_match('/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/', $placa)) {
                $q->orWhereIn('id', VeiculoVinculo::query()->whereNull('ended_at')
                    ->whereIn('veiculo_id', Veiculo::query()->where('plate_normalized', $placa)->select('id'))->select('pessoa_id'));
            }

            $q->orWhereIn('id', Vinculo::query()->whereNull('ended_at')
                ->whereHas('imovel', fn (Builder $i) => $i->whereRaw('upper(codigo) = ?', [strtoupper($termo)]))->select('pessoa_id'));
        });
    }

    private function autorizacoesVigentes(): Builder
    {
        return AutorizacaoAcesso::query()
            ->where('status', 'ativa')
            ->where('inicio', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('fim')->orWhere('fim', '>', now()));
    }

    /** @return array{0: string, 1: string} */
    private function situacao(?string $status): array
    {
        return match ($status) {
            'ativo' => ['Ativo', 'success'],
            'bloqueado' => ['Bloqueado', 'danger'],
            'inativo' => ['Inativo', 'neutral'],
            null => ['—', 'neutral'],
            default => [ucfirst(str_replace('_', ' ', $status)), 'warning'],
        };
    }

    private function podeVerFoto(): bool
    {
        return (bool) Auth::user()?->hasPermission('arquivos.sensiveis.visualizar');
    }

    public function render(): View
    {
        return view('livewire.pessoa-consulta')->layout('components.layouts.app', [
            'title' => 'Pessoas',
            'heading' => 'Pessoas',
            'headingDescription' => 'Consulte quem está cadastrado, veja a ficha e corrija os dados',
        ]);
    }
}
