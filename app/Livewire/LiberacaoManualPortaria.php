<?php

namespace App\Livewire;

use App\Integracoes\Aplicacao\ComandosAbertura;
use App\Integracoes\Aplicacao\LiberacaoManual;
use App\Integracoes\Aplicacao\ProcessadorOperacoes;
use App\Integracoes\Aplicacao\TurnosOperacionais;
use App\Integracoes\Dominio\Enums\Direcao;
use App\Integracoes\Dominio\Enums\TipoPontoAcesso;
use App\Integracoes\Dominio\Excecoes\PayloadDivergente;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\HistoricoAcesso;
use App\Models\Pessoa;
use App\Models\PessoaDocumento;
use App\Models\PontoAcesso;
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
 * Área "Liberação manual" do Modo Portaria (ADR-017 1.1.0, SDV-OPE-018).
 *
 * O posto vem do turno ativo. O componente guarda só ids e dados
 * operacionais; a cada ação o backend relê pessoa e equipamento na
 * implantação atual. Endereço, porta, certificado e credencial nunca passam
 * pelo navegador.
 */
class LiberacaoManualPortaria extends Component
{
    public string $busca = '';

    public ?string $pessoaId = null;

    public string $direcao = 'entrada';

    public ?string $equipamentoId = null;

    /** Mostra "Outros pontos de acesso" (permissão ampliada). */
    public bool $mostrarOutros = false;

    public string $motivo = '';

    /** Checkbox "Liberação excepcional — prosseguir sem autorização regular confirmada". */
    public bool $excepcional = false;

    public string $observacao = '';

    public string $identificacao = '';

    public bool $confirmando = false;

    public ?string $chave = null;

    public ?string $historicoId = null;

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

    public function updatedDirecao(): void
    {
        $this->equipamentoId = null;
        $this->confirmando = false;
    }

    public function updatedEquipamentoId(): void
    {
        $this->confirmando = false;
    }

    public function updatedExcepcional(): void
    {
        $this->confirmando = false;
        if ($this->excepcional && ! $this->operador()->hasPermission(ComandosAbertura::PERMISSAO_LIBERACAO_EXCEPCIONAL)) {
            $this->excepcional = false;
            $this->addError('excepcional', 'Você não tem a permissão de liberação excepcional.');
        }
    }

    public function revisar(LiberacaoManual $liberacoes, TurnosOperacionais $turnos): void
    {
        $operador = $this->operador();
        $posto = $turnos->turnoAtivo($operador)?->posto;
        if ($posto === null) {
            $this->addError('liberacao', 'Selecione o posto em que você está trabalhando.');

            return;
        }

        $this->validate(array_merge([
            'direcao' => ['required', 'in:entrada,saida'],
            'equipamentoId' => ['required', 'string'],
            'observacao' => ['nullable', 'string', 'max:1000'],
        ], $this->excepcional ? [
            'identificacao' => ['nullable', 'string', 'max:200'],
        ] : [
            'pessoaId' => ['required', 'string'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ]), [
            'pessoaId.required' => 'Selecione a pessoa ou marque a liberação excepcional.',
            'equipamentoId.required' => 'Escolha o ponto e o equipamento que serão acionados.',
        ], ['equipamentoId' => 'equipamento']);

        $disponiveis = $liberacoes->equipamentosDisponiveis($operador, $posto, $this->direcaoEnum());
        $todos = [...$disponiveis['preferenciais'], ...$disponiveis['outros']];
        if (! collect($todos)->contains(fn (array $i) => $i['equipamento']->id === $this->equipamentoId)) {
            $this->addError('equipamentoId', 'Este equipamento não está disponível para você nesta direção.');

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

    public function confirmar(LiberacaoManual $liberacoes, ProcessadorOperacoes $processador, TurnosOperacionais $turnos): void
    {
        $operador = $this->operador();
        if (! $this->confirmando || $this->chave === null || $this->equipamentoId === null) {
            return;
        }

        try {
            $evento = $liberacoes->solicitar($operador, [
                'equipamento_id' => $this->equipamentoId,
                'direcao' => $this->direcao,
                'pessoa_id' => $this->pessoaId,
                'motivo' => $this->motivo,
                'excepcional' => $this->excepcional,
                'observacao' => $this->observacao,
                'identificacao' => $this->identificacao,
                // A tela só chega aqui depois de mostrar a confirmação destacada.
                'confirmou_fora_do_posto' => true,
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
        $this->reset(['busca', 'pessoaId', 'equipamentoId', 'motivo', 'observacao', 'identificacao', 'excepcional', 'mostrarOutros', 'confirmando', 'chave', 'historicoId']);
        $this->resetErrorBag();
    }

    public function render(LiberacaoManual $liberacoes, TurnosOperacionais $turnos): View
    {
        $operador = $this->operador();
        $podeLiberar = $operador->hasPermission(ComandosAbertura::PERMISSAO_LIBERACAO_MANUAL);
        $turno = $podeLiberar ? $turnos->turnoAtivo($operador) : null;
        $posto = $turno?->posto;
        $disponiveis = $posto ? $liberacoes->equipamentosDisponiveis($operador, $posto, $this->direcaoEnum()) : ['preferenciais' => [], 'outros' => []];

        // Um único preferencial compatível vem pré-selecionado, sempre visível.
        if ($this->equipamentoId === null && count($disponiveis['preferenciais']) === 1) {
            $this->equipamentoId = $disponiveis['preferenciais'][0]['equipamento']->id;
        }
        $escolhido = collect([...$disponiveis['preferenciais'], ...$disponiveis['outros']])
            ->first(fn (array $i) => $i['equipamento']->id === $this->equipamentoId);

        return view('livewire.liberacao-manual-portaria', [
            'habilitada' => (bool) config('integracoes.liberacao_operacional_habilitada'),
            'podeLiberar' => $podeLiberar,
            'podeOutros' => $operador->hasPermission(ComandosAbertura::PERMISSAO_LIBERAR_OUTROS_RELES),
            'podeExcepcional' => $operador->hasPermission(ComandosAbertura::PERMISSAO_LIBERACAO_EXCEPCIONAL),
            'observacaoObrigatoria' => (bool) config('integracoes.liberacao_excepcional_observacao_obrigatoria'),
            'turno' => $turno,
            'posto' => $posto,
            'preferenciais' => $disponiveis['preferenciais'],
            'outros' => $disponiveis['outros'],
            'escolhido' => $escolhido,
            'indisponiveis' => $posto && $disponiveis['preferenciais'] === [] ? $this->indisponiveis($liberacoes, $operador, $posto) : [],
            'resultadosBusca' => $this->pessoaId === null ? $this->buscarPessoas() : collect(),
            'pessoa' => $this->pessoaId ? $this->resumoPessoa($liberacoes, $operador) : null,
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
            'nome' => $pessoa->nomeExibicao(),
            'iniciais' => collect(explode(' ', $pessoa->nome))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode(''),
            'documento' => $documento === null ? 'Não informado' : ($revelar ? $documento : $this->mascarar($documento)),
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

    /**
     * Por que cada terminal do posto não aparece (nunca IP, série ou dado técnico sensível).
     *
     * @return list<array{nome: string, ponto: string, motivos: list<string>}>
     */
    private function indisponiveis(LiberacaoManual $liberacoes, User $operador, Posto $posto): array
    {
        $pontos = $posto->principal() ? PontoAcesso::query()->orderBy('nome')->get() : $posto->pontosVigentes()->orderBy('nome')->get();

        $lista = [];
        foreach ($pontos as $ponto) {
            if ($ponto->tipo === TipoPontoAcesso::Bancada) {
                continue;
            }
            $equipamento = $ponto->vinculoVigente?->equipamento;
            $lista[] = [
                'nome' => $equipamento?->nome ?? 'Sem terminal',
                'ponto' => $ponto->nome,
                'motivos' => $equipamento
                    ? array_values($liberacoes->bloqueios($equipamento, $operador, $posto, $this->direcaoEnum()))
                    : ['O ponto não tem terminal vinculado.'],
            ];
        }

        return $lista;
    }

    private function mascarar(string $documento): string
    {
        $digitos = preg_replace('/\D/', '', $documento) ?? '';

        return strlen($digitos) === 11 ? '***.***.'.substr($digitos, 6, 3).'-**' : 'Documento protegido';
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
