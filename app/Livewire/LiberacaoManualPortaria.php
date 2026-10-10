<?php

namespace App\Livewire;

use App\Integracoes\Aplicacao\ComandosAbertura;
use App\Integracoes\Aplicacao\LiberacaoManual;
use App\Integracoes\Aplicacao\ProcessadorOperacoes;
use App\Integracoes\Dominio\Enums\Direcao;
use App\Integracoes\Dominio\Excecoes\PayloadDivergente;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\Equipamento;
use App\Models\HistoricoAcesso;
use App\Models\Pessoa;
use App\Models\PessoaDocumento;
use App\Models\Posto;
use App\Models\PreRegistration;
use App\Models\User;
use App\Models\Vinculo;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Área "Liberação manual" do Modo Portaria (ADR-017).
 *
 * O componente só guarda ids e dados operacionais. A cada ação o backend
 * relê posto, pessoa e equipamento na implantação atual; endereço, porta,
 * certificado e credencial nunca passam pelo navegador.
 */
class LiberacaoManualPortaria extends Component
{
    /** 'pessoa' (liberação normal) ou 'excepcional' (sem cadastro, auditoria reforçada) */
    public string $modo = 'pessoa';

    public string $busca = '';

    public ?string $pessoaId = null;

    public ?string $postoId = null;

    public string $direcao = 'entrada';

    public ?string $equipamentoId = null;

    public string $motivo = '';

    public string $identificacao = '';

    public string $justificativa = '';

    public bool $confirmando = false;

    public ?string $chave = null;

    public ?string $historicoId = null;

    public function mount(LiberacaoManual $liberacoes): void
    {
        $postos = $liberacoes->postosDoOperador($this->operador());
        $this->postoId = $postos->count() === 1 ? $postos->first()->id : null;
    }

    public function alterarModo(string $modo): void
    {
        $this->modo = $modo === 'excepcional' ? 'excepcional' : 'pessoa';
        $this->pessoaId = null;
        $this->confirmando = false;
    }

    public function selecionarPessoa(string $id, AuditService $audit): void
    {
        $this->pessoaId = Pessoa::query()->findOrFail($id)->id;
        $this->busca = '';
        $this->confirmando = false;

        // Documento sem máscara só para quem pode revelar, e a revelação fica auditada.
        if ($this->operador()->hasPermission('dados-sensiveis.revelar')) {
            $audit->record(
                action: 'revelou_dado_sensivel',
                module: 'acessos',
                entityType: 'pessoas',
                entityId: $this->pessoaId,
                classification: 'restrita',
                metadata: ['categoria' => 'documento', 'contexto' => 'liberacao_manual'],
            );
        }
    }

    public function updatedPostoId(): void
    {
        $this->equipamentoId = null;
        $this->confirmando = false;
    }

    public function updatedDirecao(): void
    {
        $this->equipamentoId = null;
        $this->confirmando = false;
    }

    public function revisar(LiberacaoManual $liberacoes): void
    {
        $operador = $this->operador();

        $this->validate(array_merge([
            'postoId' => ['required', 'string'],
            'direcao' => ['required', 'in:entrada,saida'],
            'equipamentoId' => ['required', 'string'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ], $this->modo === 'excepcional' ? [
            'identificacao' => ['required', 'string', 'min:3', 'max:200'],
            'justificativa' => ['required', 'string', 'min:20', 'max:1000'],
        ] : [
            'pessoaId' => ['required', 'string'],
        ]), [
            'pessoaId.required' => 'Selecione a pessoa. Sem cadastro, use a liberação excepcional.',
            'equipamentoId.required' => 'Escolha o ponto e o equipamento que serão acionados.',
        ], [
            'postoId' => 'posto', 'equipamentoId' => 'equipamento', 'identificacao' => 'identificação',
        ]);

        // O equipamento escolhido precisa estar entre os permitidos agora.
        $permitido = collect($liberacoes->equipamentosPermitidos($operador, $this->posto(), $this->direcaoEnum()))
            ->contains(fn (array $item) => $item['equipamento']->id === $this->equipamentoId);
        if (! $permitido) {
            $this->addError('equipamentoId', 'Este equipamento não está disponível para o seu posto e a direção escolhida.');

            return;
        }

        $this->chave = 'liberacao-manual:'.Str::uuid7();
        $this->confirmando = true;
    }

    public function cancelar(): void
    {
        $this->confirmando = false;
        $this->chave = null;
    }

    public function confirmar(LiberacaoManual $liberacoes, ProcessadorOperacoes $processador): void
    {
        $operador = $this->operador();
        if (! $this->confirmando || $this->chave === null) {
            return;
        }

        try {
            $evento = $liberacoes->solicitar($operador, [
                'posto_id' => (string) $this->postoId,
                'equipamento_id' => (string) $this->equipamentoId,
                'direcao' => $this->direcao,
                'pessoa_id' => $this->modo === 'pessoa' ? $this->pessoaId : null,
                'motivo' => $this->motivo,
                'excepcional' => $this->modo === 'excepcional',
                'identificacao' => $this->identificacao,
                'justificativa' => $this->justificativa,
            ], $this->chave);

            if ($evento->operacao_integracao_id !== null) {
                $processador->processar($evento->operacao_integracao_id);
            }
        } catch (RegraIntegracaoViolada|PayloadDivergente $e) {
            $this->confirmando = false;
            $this->addError('liberacao', $e->getMessage());

            return;
        }

        $this->historicoId = $evento->id;
        $this->confirmando = false;
    }

    public function novaLiberacao(): void
    {
        $this->reset(['busca', 'pessoaId', 'equipamentoId', 'motivo', 'identificacao', 'justificativa', 'confirmando', 'chave', 'historicoId']);
        $this->modo = 'pessoa';
        $this->resetErrorBag();
    }

    public function render(LiberacaoManual $liberacoes): View
    {
        $operador = $this->operador();
        $podeLiberar = $operador->hasPermission(ComandosAbertura::PERMISSAO_LIBERACAO_MANUAL);
        $posto = $this->postoId ? Posto::query()->find($this->postoId) : null;
        $permitidos = $posto && $podeLiberar ? $liberacoes->equipamentosPermitidos($operador, $posto, $this->direcaoEnum()) : [];

        // Um único equipamento compatível vem pré-selecionado, sempre visível.
        if ($this->equipamentoId === null && count($permitidos) === 1) {
            $this->equipamentoId = $permitidos[0]['equipamento']->id;
        }
        $escolhido = collect($permitidos)->first(fn (array $item) => $item['equipamento']->id === $this->equipamentoId);

        return view('livewire.liberacao-manual-portaria', [
            'habilitada' => (bool) config('integracoes.liberacao_operacional_habilitada'),
            'podeLiberar' => $podeLiberar,
            'podeExcepcional' => $operador->hasPermission(ComandosAbertura::PERMISSAO_LIBERACAO_EXCEPCIONAL),
            'postos' => $podeLiberar ? $liberacoes->postosDoOperador($operador) : collect(),
            'permitidos' => $permitidos,
            'escolhido' => $escolhido,
            'resultadosBusca' => $this->modo === 'pessoa' && $this->pessoaId === null ? $this->buscarPessoas() : collect(),
            'pessoa' => $this->modo === 'pessoa' && $this->pessoaId ? $this->resumoPessoa($liberacoes, $operador) : null,
            'evento' => $this->historicoId ? HistoricoAcesso::query()->with(['operacaoIntegracao', 'equipamento', 'pontoAcessoRegistro'])->find($this->historicoId) : null,
        ]);
    }

    /** @return Collection<int, Pessoa> */
    private function buscarPessoas()
    {
        $termo = trim($this->busca);
        if (mb_strlen($termo) < 3) {
            return collect();
        }

        $digitos = preg_replace('/\D/', '', $termo) ?? '';

        return Pessoa::query()
            ->where(function ($q) use ($termo, $digitos) {
                $q->where('nome', 'like', '%'.$termo.'%');
                if (strlen($digitos) >= 3) {
                    $q->orWhereHas('documentos', fn ($d) => $d->where('valor_normalizado', 'like', '%'.$digitos.'%')->whereNull('ended_at'));
                }
            })
            ->orderBy('nome')
            ->limit(8)
            ->get();
    }

    /** @return array<string, mixed> */
    private function resumoPessoa(LiberacaoManual $liberacoes, User $operador): array
    {
        $pessoa = Pessoa::query()->with('documentos')->findOrFail($this->pessoaId);
        $vinculo = Vinculo::query()->where('pessoa_id', $pessoa->id)->whereNull('ended_at')->with('imovel')->first();
        $documento = $pessoa->documentos->whereNull('ended_at')->first()?->valor_apresentacao;
        $revelar = $operador->hasPermission('dados-sensiveis.revelar');

        return [
            'pessoa' => $pessoa,
            'nome' => $pessoa->nomeExibicao(),
            'iniciais' => collect(explode(' ', $pessoa->nome))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode(''),
            'documento' => $documento === null ? 'Não informado' : ($revelar ? $documento : $this->mascarar($documento)),
            'documentoRevelado' => $revelar,
            'perfil' => $vinculo ? ucfirst((string) $vinculo->tipo) : 'Sem vínculo',
            'imovel' => $vinculo?->imovel?->label() ?? 'Não vinculado',
            'autorizacao' => $vinculo && $vinculo->ativo() ? 'Vínculo vigente' : 'Sem autorização vigente',
            'validade' => $vinculo
                ? 'desde '.$vinculo->started_at?->format('d/m/Y').($vinculo->ended_at ? ' até '.$vinculo->ended_at->format('d/m/Y') : ', sem data de término')
                : '—',
            'restricoes' => $liberacoes->restricoesDaPessoa($pessoa),
            'arquivos' => $this->arquivosProtegidos($pessoa),
        ];
    }

    /** @return array{document: mixed, selfie: mixed, pre_registration_id: ?string} */
    private function arquivosProtegidos(Pessoa $pessoa): array
    {
        $apresentado = PessoaDocumento::query()->where('pessoa_id', $pessoa->id)->where('status', 'ativo')->whereNull('ended_at')->value('valor_apresentacao');
        $pre = $apresentado ? PreRegistration::query()->where('document', $apresentado)->where('status', 'aprovado')->with('fileLinks.file')->latest('submitted_at')->first() : null;

        return [
            'document' => $pre?->currentFileLink('documento'),
            'selfie' => $pre?->currentFileLink('selfie'),
            'pre_registration_id' => $pre?->id,
        ];
    }

    private function mascarar(string $documento): string
    {
        $digitos = preg_replace('/\D/', '', $documento) ?? '';

        return strlen($digitos) === 11 ? '***.***.'.substr($digitos, 6, 3).'-**' : 'Documento protegido';
    }

    private function posto(): Posto
    {
        return Posto::query()->findOrFail($this->postoId);
    }

    private function direcaoEnum(): Direcao
    {
        return $this->direcao === 'saida' ? Direcao::Saida : Direcao::Entrada;
    }

    private function operador(): User
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof User, 403);

        return $usuario;
    }
}
