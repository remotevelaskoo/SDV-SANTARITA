<?php

namespace App\Livewire;

use App\Integracoes\Aplicacao\CadastroPostos;
use App\Integracoes\Aplicacao\TurnosOperacionais;
use App\Integracoes\Dominio\Enums\StatusPontoAcesso;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\PontoAcesso;
use App\Models\Posto;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Postos da portaria (ADR-017, SDV-OPE-018 §14): quem está em cada posto
 * agora, os pontos que cada um atende e a liberação de um posto preso.
 * Qualquer operador assume qualquer posto, então não há designação.
 *
 * Cadastro e pontos: `integracoes.gerenciar`. Liberar posto: `postos.liberar`
 * (Gestor e Administrador).
 */
class PostoManagement extends Component
{
    public bool $mostrarNovo = false;

    public string $codigo = '';

    public string $nome = '';

    public string $tipo = Posto::TIPO_PRINCIPAL;

    /** Posto em edição (nome e tipo). */
    public ?string $editandoId = null;

    public string $editNome = '';

    public string $editTipo = Posto::TIPO_PRINCIPAL;

    /** @var array<string, string> posto => ponto escolhido */
    public array $pontoEscolhido = [];

    /** Posto em liberação e o motivo informado. */
    public ?string $liberandoId = null;

    public string $motivoLiberacao = '';

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    public function criarPosto(CadastroPostos $cadastro): void
    {
        $ok = $this->executar(fn () => $cadastro->criarPosto($this->codigo, $this->nome, $this->gestor(), $this->tipo), 'Posto cadastrado');
        if ($ok) {
            $this->reset(['codigo', 'nome', 'tipo', 'mostrarNovo']);
        }
    }

    public function editar(string $postoId): void
    {
        $this->gestor();
        $posto = Posto::query()->findOrFail($postoId);
        $this->editandoId = $posto->id;
        $this->editNome = $posto->nome;
        $this->editTipo = $posto->tipo;
    }

    public function salvarEdicao(CadastroPostos $cadastro): void
    {
        $ator = $this->gestor();
        $posto = Posto::query()->findOrFail($this->editandoId);
        if ($this->executar(fn () => $cadastro->editarPosto($posto, $this->editNome, $this->editTipo, $ator), 'Posto atualizado')) {
            $this->editandoId = null;
        }
    }

    public function cancelarEdicao(): void
    {
        $this->editandoId = null;
    }

    public function alterarStatus(string $postoId, bool $ativo, CadastroPostos $cadastro): void
    {
        $ator = $this->gestor();
        $this->executar(fn () => $cadastro->alterarStatus(Posto::query()->findOrFail($postoId), $ativo, $ator), $ativo ? 'Posto reativado' : 'Posto desativado');
    }

    public function vincularPonto(string $postoId, CadastroPostos $cadastro): void
    {
        $ator = $this->gestor();
        $pontoId = $this->pontoEscolhido[$postoId] ?? '';
        if ($pontoId === '') {
            $this->feedback = ['variant' => 'danger', 'title' => 'Escolha o ponto', 'message' => 'Selecione o ponto de acesso na lista antes de vincular.'];

            return;
        }
        if ($this->executar(fn () => $cadastro->vincularPonto(Posto::query()->findOrFail($postoId), PontoAcesso::query()->findOrFail($pontoId), $ator), 'Ponto vinculado ao posto')) {
            unset($this->pontoEscolhido[$postoId]);
        }
    }

    public function desvincularPonto(string $postoId, string $pontoId, CadastroPostos $cadastro): void
    {
        $ator = $this->gestor();
        $this->executar(fn () => $cadastro->desvincularPonto(Posto::query()->findOrFail($postoId), PontoAcesso::query()->findOrFail($pontoId), $ator), 'Ponto removido do posto');
    }

    public function iniciarLiberacao(string $postoId): void
    {
        $this->liberador();
        $this->liberandoId = $postoId;
        $this->motivoLiberacao = '';
    }

    public function cancelarLiberacao(): void
    {
        $this->liberandoId = null;
        $this->motivoLiberacao = '';
    }

    public function liberar(TurnosOperacionais $turnos): void
    {
        $ator = $this->liberador();
        $posto = Posto::query()->findOrFail($this->liberandoId);
        if ($this->executar(fn () => $turnos->liberar($posto, $ator, $this->motivoLiberacao), 'Posto liberado', 'O turno foi encerrado e o caixa, se estava aberto, foi fechado como "aguardando conferência".')) {
            $this->cancelarLiberacao();
        }
    }

    public function render(TurnosOperacionais $turnos): View
    {
        $usuario = $this->usuario();
        abort_unless($usuario->hasPermission('integracoes.gerenciar') || $usuario->hasPermission(TurnosOperacionais::PERMISSAO_LIBERAR), 403);

        return view('livewire.posto-management', [
            'postos' => Posto::query()->with(['pontosVigentes'])
                ->orderByRaw("case status when 'ativo' then 0 else 1 end")
                ->orderByRaw("case tipo when 'principal' then 0 when 'veiculos' then 1 when 'pedestres' then 2 else 3 end")
                ->orderBy('nome')->get(),
            'ocupacoes' => $turnos->ocupacoes(),
            'pontos' => PontoAcesso::query()->where('status', '!=', StatusPontoAcesso::Inativo->value)->orderBy('nome')->get(),
            'podeGerenciar' => $usuario->hasPermission('integracoes.gerenciar'),
            'podeLiberar' => $usuario->hasPermission(TurnosOperacionais::PERMISSAO_LIBERAR),
        ])->layout('components.layouts.app', [
            'title' => 'Postos',
            'heading' => 'Postos',
            'headingDescription' => 'Quem está em cada posto agora e quais pontos de acesso cada posto atende',
        ]);
    }

    private function executar(callable $acao, string $titulo, string $mensagem = 'Alteração registrada na auditoria.'): bool
    {
        try {
            $acao();
            $this->feedback = ['variant' => 'success', 'title' => $titulo, 'message' => $mensagem];

            return true;
        } catch (RegraIntegracaoViolada $e) {
            $this->feedback = ['variant' => 'danger', 'title' => 'Não foi possível concluir', 'message' => $e->getMessage()];

            return false;
        }
    }

    private function gestor(): User
    {
        $usuario = $this->usuario();
        abort_unless($usuario->hasPermission('integracoes.gerenciar'), 403);

        return $usuario;
    }

    private function liberador(): User
    {
        $usuario = $this->usuario();
        abort_unless($usuario->hasPermission(TurnosOperacionais::PERMISSAO_LIBERAR), 403);

        return $usuario;
    }

    private function usuario(): User
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof User, 403);

        return $usuario;
    }
}
