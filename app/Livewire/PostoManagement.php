<?php

namespace App\Livewire;

use App\Integracoes\Aplicacao\CadastroPostos;
use App\Integracoes\Dominio\Enums\StatusPontoAcesso;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\OperadorPosto;
use App\Models\PontoAcesso;
use App\Models\Posto;
use App\Models\User;
use App\Models\UsuarioImplantacao;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Postos da portaria (ADR-017): quais pontos cada posto atende e quais
 * operadores atuam nele. Define o que cada operador pode acionar na
 * liberação manual, sem acesso global aos equipamentos.
 */
class PostoManagement extends Component
{
    public string $codigo = '';

    public string $nome = '';

    public string $tipo = 'principal';

    /** @var array<string, string> posto => ponto escolhido */
    public array $pontoEscolhido = [];

    /** @var array<string, string> posto => operador escolhido */
    public array $operadorEscolhido = [];

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    public function criarPosto(CadastroPostos $cadastro): void
    {
        $this->executar(fn () => $cadastro->criarPosto($this->codigo, $this->nome, $this->autorizar(), $this->tipo), 'Posto cadastrado');
        $this->reset(['codigo', 'nome']);
    }

    public function vincularPonto(string $postoId, CadastroPostos $cadastro): void
    {
        $ator = $this->autorizar();
        $pontoId = $this->pontoEscolhido[$postoId] ?? '';
        if ($pontoId === '') {
            $this->feedback = ['variant' => 'danger', 'title' => 'Escolha o ponto', 'message' => 'Selecione o ponto de acesso na lista antes de vincular.'];

            return;
        }
        $this->executar(fn () => $cadastro->vincularPonto(Posto::query()->findOrFail($postoId), PontoAcesso::query()->findOrFail($pontoId), $ator), 'Ponto vinculado ao posto');
    }

    public function desvincularPonto(string $postoId, string $pontoId, CadastroPostos $cadastro): void
    {
        $ator = $this->autorizar();
        $this->executar(fn () => $cadastro->desvincularPonto(Posto::query()->findOrFail($postoId), PontoAcesso::query()->findOrFail($pontoId), $ator), 'Ponto removido do posto');
    }

    public function designarOperador(string $postoId, CadastroPostos $cadastro): void
    {
        $ator = $this->autorizar();
        $userId = (int) ($this->operadorEscolhido[$postoId] ?? 0);
        if ($userId === 0) {
            $this->feedback = ['variant' => 'danger', 'title' => 'Escolha o operador', 'message' => 'Selecione o operador na lista antes de designar.'];

            return;
        }
        $this->executar(fn () => $cadastro->designarOperador(Posto::query()->findOrFail($postoId), $this->usuarioDaImplantacao($userId), $ator), 'Operador designado');
    }

    public function encerrarDesignacao(string $postoId, int $userId, CadastroPostos $cadastro): void
    {
        $ator = $this->autorizar();
        $this->executar(fn () => $cadastro->encerrarDesignacao(Posto::query()->findOrFail($postoId), $this->usuarioDaImplantacao($userId), $ator), 'Designação encerrada');
    }

    public function render(): View
    {
        $this->autorizar();
        $idsUsuarios = UsuarioImplantacao::query()->where('status', 'ativa')->pluck('user_id');

        return view('livewire.posto-management', [
            'postos' => Posto::query()->with(['pontosVigentes'])->orderBy('nome')->get(),
            'operadores' => OperadorPosto::query()->whereNull('ended_at')->with('user')->get()->groupBy('posto_id'),
            'pontos' => PontoAcesso::query()->where('status', '!=', StatusPontoAcesso::Inativo->value)->orderBy('nome')->get(),
            'usuarios' => User::query()->whereIn('id', $idsUsuarios)->orderBy('name')->get(),
        ])->layout('components.layouts.app', [
            'title' => 'Postos e operadores',
            'heading' => 'Postos e operadores',
            'headingDescription' => 'Quais pontos cada posto atende e quais operadores podem acionar seus equipamentos',
        ]);
    }

    private function usuarioDaImplantacao(int $userId): User
    {
        abort_unless(UsuarioImplantacao::query()->where('user_id', $userId)->exists(), 404);

        return User::query()->findOrFail($userId);
    }

    private function executar(callable $acao, string $titulo): void
    {
        try {
            $acao();
            $this->feedback = ['variant' => 'success', 'title' => $titulo, 'message' => 'Alteração registrada na auditoria.'];
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
