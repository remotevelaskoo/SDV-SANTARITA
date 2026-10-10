<?php

namespace App\Livewire;

use App\Integracoes\Aplicacao\CadastroEquipamentos;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Integracoes\Dominio\Enums\StatusPontoAcesso;
use App\Integracoes\Dominio\Enums\TipoPontoAcesso;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\Equipamento;
use App\Models\PontoAcesso;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Pontos de acesso (cancela ou catraca) e o terminal que atende cada um
 * (docs/008 §21, ADR-016). Leva a facial da bancada ao ponto real: vincular,
 * homologar o relé no ponto (teste do relé) e só então ativar.
 */
class PontoAcessoManagement extends Component
{
    /** @var array{codigo: string, nome: string, tipo: string, direcao_suportada: string, localizacao: string} */
    public array $form = ['codigo' => '', 'nome' => '', 'tipo' => 'cancela', 'direcao_suportada' => 'bidirecional', 'localizacao' => ''];

    /** @var array<string, string> ponto => terminal escolhido para vincular */
    public array $terminalEscolhido = [];

    /** Motivo usado nas mudanças de situação e de vínculo (auditado). */
    public string $motivo = '';

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    public function cadastrar(CadastroEquipamentos $cadastro): void
    {
        $ator = $this->autorizar();
        $this->validate([
            'form.codigo' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/'],
            'form.nome' => ['required', 'string', 'max:120'],
            'form.tipo' => ['required', 'in:cancela,catraca'],
            'form.direcao_suportada' => ['required', 'in:entrada,saida,bidirecional'],
            'form.localizacao' => ['nullable', 'string', 'max:200'],
        ], attributes: ['form.codigo' => 'código', 'form.nome' => 'nome']);

        $this->executar(fn () => $cadastro->registrarPontoAcesso([
            ...$this->form,
            'codigo' => mb_strtoupper($this->form['codigo']),
            'localizacao' => $this->form['localizacao'] ?: null,
        ], $ator), 'Ponto de acesso cadastrado', 'O ponto começa "em implantação". Vincule o terminal antes de ativá-lo.');
        $this->form = ['codigo' => '', 'nome' => '', 'tipo' => 'cancela', 'direcao_suportada' => 'bidirecional', 'localizacao' => ''];
    }

    public function alterarSituacaoPonto(string $pontoId, string $situacao, CadastroEquipamentos $cadastro): void
    {
        $ator = $this->autorizar();
        $novo = StatusPontoAcesso::tryFrom($situacao) ?? abort(422);
        if (! $this->temMotivo()) {
            return;
        }

        $this->executar(fn () => $cadastro->alterarStatusPonto(PontoAcesso::query()->findOrFail($pontoId), $novo, $this->motivo, $ator), 'Situação do ponto alterada');
    }

    public function vincularTerminal(string $pontoId, CadastroEquipamentos $cadastro): void
    {
        $ator = $this->autorizar();
        $terminalId = $this->terminalEscolhido[$pontoId] ?? '';

        $this->executar(
            fn () => $cadastro->vincularAoPonto(Equipamento::query()->findOrFail($terminalId), PontoAcesso::query()->findOrFail($pontoId), $ator),
            'Terminal vinculado',
            'Teste o relé neste ponto (Gestão › Equipamentos) e registre a homologação antes de ativar.',
        );
    }

    public function desvincularTerminal(string $terminalId, CadastroEquipamentos $cadastro): void
    {
        $ator = $this->autorizar();
        if (! $this->temMotivo()) {
            return;
        }

        $this->executar(
            fn () => $cadastro->desvincularDoPonto(Equipamento::query()->findOrFail($terminalId), $this->motivo, $ator),
            'Terminal desvinculado',
            'A homologação do relé foi desfeita: o relé precisa ser testado de novo no novo ponto.',
        );
    }

    public function alterarSituacaoTerminal(string $terminalId, string $situacao, CadastroEquipamentos $cadastro): void
    {
        $ator = $this->autorizar();
        $novo = StatusEquipamento::tryFrom($situacao);
        if (! in_array($novo, [StatusEquipamento::Ativo, StatusEquipamento::EmHomologacao, StatusEquipamento::Manutencao], true)) {
            abort(422);
        }
        if (! $this->temMotivo()) {
            return;
        }

        $this->executar(fn () => $cadastro->alterarStatus(Equipamento::query()->findOrFail($terminalId), $novo, $this->motivo, $ator), 'Situação do terminal alterada');
    }

    public function render(): View
    {
        $this->autorizar();

        return view('livewire.ponto-acesso-management', [
            'pontos' => PontoAcesso::query()->with('vinculoVigente.equipamento')->orderBy('nome')->get(),
            'terminaisLivres' => Equipamento::query()
                ->where('status', '!=', StatusEquipamento::Inativo->value)
                ->whereDoesntHave('vinculoVigente')
                ->orderBy('nome')
                ->get(),
            'bancada' => TipoPontoAcesso::Bancada,
        ])->layout('components.layouts.app', [
            'title' => 'Pontos de acesso',
            'heading' => 'Pontos de acesso',
            'headingDescription' => 'Cancelas e catracas da implantação e o terminal que atende cada uma',
        ]);
    }

    private function temMotivo(): bool
    {
        if (mb_strlen(trim($this->motivo)) < 5) {
            $this->addError('motivo', 'Informe o motivo (mínimo 5 caracteres) antes de alterar situação ou vínculo.');

            return false;
        }

        return true;
    }

    private function executar(callable $acao, string $titulo, string $mensagem = 'Alteração registrada na auditoria.'): void
    {
        try {
            $acao();
            $this->feedback = ['variant' => 'success', 'title' => $titulo, 'message' => $mensagem];
            $this->motivo = '';
            $this->resetErrorBag();
        } catch (RegraIntegracaoViolada $e) {
            $this->feedback = ['variant' => 'danger', 'title' => 'Não foi possível concluir', 'message' => $e->getMessage()];
        }
    }

    private function autorizar(): User
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof User && $usuario->hasPermission('integracoes.gerenciar'), 403);

        return $usuario;
    }
}
