<?php

namespace App\Livewire;

use App\Integracoes\Aplicacao\CadastroEquipamentos;
use App\Integracoes\Aplicacao\CapturaImagemEquipamento;
use App\Integracoes\Aplicacao\ComandosAbertura;
use App\Integracoes\Aplicacao\DiagnosticoEquipamento;
use App\Integracoes\Aplicacao\ProcessadorOperacoes;
use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\EstadoOutbox;
use App\Integracoes\Dominio\Enums\EstadoSaude;
use App\Integracoes\Dominio\Enums\OperacaoIntegracao as TipoOperacao;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Integracoes\Dominio\Enums\StatusPontoAcesso;
use App\Integracoes\Dominio\Enums\TipoPontoAcesso;
use App\Integracoes\Dominio\Excecoes\PayloadDivergente;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Integracoes\Infra\LeitorCertificadoTls;
use App\Integracoes\Infra\RegistroAdaptadores;
use App\Models\AuditoriaEvento;
use App\Models\Equipamento;
use App\Models\IntegracaoOcorrencia;
use App\Models\OperacaoIntegracao;
use App\Models\PontoAcesso;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Administração de equipamentos: cadastro, teste de conexão, capacidades,
 * certificado, imagem estática e teste do relé (ADR-016 §11).
 *
 * O navegador só conversa com o SDV. Nenhum endereço autenticado, usuário ou
 * senha do terminal chega ao HTML: a senha é recebida uma vez, repassada ao
 * cadastro e apagada do estado do componente antes de qualquer validação.
 */
class EquipamentoManagement extends Component
{
    public string $mode = 'list';

    public ?string $selecionadoId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public string $usuarioTecnico = '';

    public string $senhaTecnica = '';

    public bool $trocandoSenha = false;

    /** @var array<string, mixed>|null */
    public ?array $certificadoLido = null;

    /** @var array<string, mixed>|null */
    public ?array $ultimaCaptura = null;

    public bool $confirmandoLiberacao = false;

    public ?string $chaveLiberacao = null;

    public string $motivoLiberacao = '';

    public bool $cienteLiberacao = false;

    public ?string $operacaoLiberacaoId = null;

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    public function mount(): void
    {
        $this->autorizar();
    }

    // ---- Cadastro -----------------------------------------------------------

    public function novoEquipamento(): void
    {
        $this->autorizar();
        $padrao = (array) config('integracoes.homologacao_padrao');

        $this->form = [
            'nome' => $padrao['nome'] ?? '',
            'fabricante' => $padrao['fabricante'] ?? '',
            'modelo' => $padrao['modelo'] ?? '',
            'numero_serie' => '',
            'firmware_versao' => '',
            'endereco_rede' => $padrao['endereco_rede'] ?? '',
            'porta_rede' => '',
            'esquema' => $padrao['esquema'] ?? 'https',
            'protocolo' => $padrao['protocolo'] ?? '',
            'adaptador' => $padrao['adaptador'] ?? '',
            'direcao' => 'entrada',
            'timeout_segundos' => 5,
            'modulo_seguro_rs485' => true,
            'ponto_id' => '',
        ];
        $this->usuarioTecnico = (string) ($padrao['usuario_tecnico'] ?? '');
        $this->senhaTecnica = '';
        $this->feedback = null;
        $this->resetErrorBag();
        $this->mode = 'form';
    }

    public function salvarEquipamento(CadastroEquipamentos $cadastro): void
    {
        // A senha sai do estado do componente antes de qualquer retorno.
        $senha = $this->senhaTecnica;
        $this->senhaTecnica = '';
        $ator = $this->autorizar();

        $this->validate([
            'form.nome' => ['required', 'string', 'max:120'],
            'form.fabricante' => ['required', 'string', 'max:80'],
            'form.modelo' => ['required', 'string', 'max:80'],
            'form.numero_serie' => ['nullable', 'string', 'max:120'],
            'form.firmware_versao' => ['nullable', 'string', 'max:80'],
            'form.endereco_rede' => ['required', 'ip'],
            'form.porta_rede' => ['nullable', 'integer', 'between:1,65535'],
            'form.esquema' => ['required', 'in:http,https'],
            'form.protocolo' => ['required', 'string', 'max:40'],
            'form.adaptador' => ['required', 'string'],
            'form.direcao' => ['required', 'in:entrada,saida,bidirecional'],
            'form.timeout_segundos' => ['required', 'integer', 'between:1,30'],
            'usuarioTecnico' => ['required', 'string', 'max:32'],
        ], attributes: ['form.endereco_rede' => 'endereço IP', 'usuarioTecnico' => 'usuário técnico']);

        if ($senha === '' && $this->form['adaptador'] !== 'simulador') {
            $this->addError('senhaTecnica', 'Informe a senha técnica do terminal.');

            return;
        }

        try {
            $equipamento = DB::transaction(function () use ($cadastro, $ator, $senha): Equipamento {
                $ponto = $this->pontoParaCadastro($cadastro, $ator);
                $equipamento = $cadastro->registrarEquipamento([
                    ...$this->form,
                    'tipo' => 'terminal_facial',
                    'numero_serie' => $this->form['numero_serie'] ?: null,
                    'firmware_versao' => $this->form['firmware_versao'] ?: null,
                    'porta_rede' => $this->form['porta_rede'] === '' ? null : (int) $this->form['porta_rede'],
                    'timeout_segundos' => (int) $this->form['timeout_segundos'],
                    'modulo_seguro_rs485' => (bool) $this->form['modulo_seguro_rs485'],
                ], $ator);

                $cadastro->vincularAoPonto($equipamento, $ponto, $ator);
                if ($senha !== '') {
                    $cadastro->definirSenhaTecnica($equipamento, $this->usuarioTecnico, $senha, $ator);
                }

                return $cadastro->alterarStatus($equipamento->refresh(), StatusEquipamento::EmHomologacao, 'cadastro para homologação em bancada', $ator);
            });
        } catch (RegraIntegracaoViolada $e) {
            $this->erro('Não foi possível cadastrar', $e->getMessage());

            return;
        }

        $this->selecionadoId = $equipamento->id;
        $this->mode = 'detail';
        $this->sucesso('Equipamento cadastrado', 'Cadastrado em homologação. A senha foi gravada cifrada e não será exibida.');
    }

    public function selecionar(string $id): void
    {
        $this->autorizar();
        $this->selecionadoId = $this->equipamento($id)->id;
        $this->reiniciarPainel();
        $this->mode = 'detail';
    }

    public function voltar(): void
    {
        $this->reiniciarPainel();
        $this->selecionadoId = null;
        $this->mode = 'list';
    }

    public function iniciarTrocaSenha(): void
    {
        $this->autorizar();
        $this->usuarioTecnico = (string) config('integracoes.homologacao_padrao.usuario_tecnico', '');
        $this->senhaTecnica = '';
        $this->trocandoSenha = true;
    }

    public function salvarSenha(CadastroEquipamentos $cadastro): void
    {
        $senha = $this->senhaTecnica;
        $this->senhaTecnica = '';
        $ator = $this->autorizar();

        try {
            $cadastro->definirSenhaTecnica($this->equipamento(), $this->usuarioTecnico, $senha, $ator);
        } catch (RegraIntegracaoViolada $e) {
            $this->erro('Credencial não alterada', $e->getMessage());

            return;
        }

        $this->trocandoSenha = false;
        $this->sucesso('Credencial substituída', 'A nova senha foi gravada cifrada. A anterior foi desativada.');
    }

    public function colocarEmHomologacao(CadastroEquipamentos $cadastro): void
    {
        $ator = $this->autorizar();

        try {
            $cadastro->alterarStatus($this->equipamento(), StatusEquipamento::EmHomologacao, 'homologação em bancada', $ator);
            $this->sucesso('Situação alterada', 'O terminal está em homologação.');
        } catch (RegraIntegracaoViolada $e) {
            $this->erro('Situação não alterada', $e->getMessage());
        }
    }

    // ---- Certificado --------------------------------------------------------

    public function lerCertificado(LeitorCertificadoTls $leitor): void
    {
        $this->autorizar();
        $equipamento = $this->equipamento();

        try {
            $this->certificadoLido = $leitor->ler($equipamento->endereco_rede, $equipamento->porta_rede ?? 443, min(5, $equipamento->timeout_segundos));
        } catch (RegraIntegracaoViolada $e) {
            $this->erro('Certificado não lido', $e->getMessage());
        }
    }

    public function confiarCertificado(CadastroEquipamentos $cadastro): void
    {
        $ator = $this->autorizar();
        if ($this->certificadoLido === null) {
            return;
        }

        try {
            $cadastro->confiarCertificado($this->equipamento(), (string) $this->certificadoLido['pin_sha256'], (string) $this->certificadoLido['impressao_sha256'], $ator);
            $this->certificadoLido = null;
            $this->sucesso('Certificado confiado', 'Somente este certificado será aceito para este terminal.');
        } catch (RegraIntegracaoViolada $e) {
            $this->erro('Certificado não confiado', $e->getMessage());
        }
    }

    // ---- Diagnóstico --------------------------------------------------------

    public function testarConexao(DiagnosticoEquipamento $diagnostico, ProcessadorOperacoes $processador): void
    {
        $this->executarDiagnostico(fn (Equipamento $e, User $u) => $diagnostico->testarConexao($e, $u), $processador, 'Teste de conexão');
    }

    public function consultarCapacidades(DiagnosticoEquipamento $diagnostico, ProcessadorOperacoes $processador): void
    {
        $this->executarDiagnostico(fn (Equipamento $e, User $u) => $diagnostico->consultarCapacidades($e, $u), $processador, 'Consulta de capacidades');
    }

    public function atualizarImagem(CapturaImagemEquipamento $capturas): void
    {
        $ator = $this->autorizar();

        try {
            $this->ultimaCaptura = $capturas->capturar($this->equipamento(), $ator);
        } catch (RegraIntegracaoViolada $e) {
            $this->erro('Imagem não atualizada', $e->getMessage());

            return;
        }

        $this->ultimaCaptura['resultado'] === ResultadoEquipamento::Confirmado->value
            ? $this->sucesso('Imagem atualizada', 'Imagem estática capturada pelo SDV.')
            : $this->erro('Imagem indisponível', (string) $this->ultimaCaptura['mensagem']);
    }

    // ---- Liberação remota (teste do relé) -------------------------------------

    public function abrirLiberacao(): void
    {
        $this->autorizarLiberacao();
        $this->chaveLiberacao = 'liberacao:'.Str::uuid7();
        $this->motivoLiberacao = '';
        $this->cienteLiberacao = false;
        $this->confirmandoLiberacao = true;
    }

    public function cancelarLiberacao(): void
    {
        $this->confirmandoLiberacao = false;
        $this->chaveLiberacao = null;
    }

    public function liberarAcesso(ComandosAbertura $comandos, ProcessadorOperacoes $processador): void
    {
        $ator = $this->autorizarLiberacao();

        $this->validate([
            'motivoLiberacao' => ['required', 'string', 'min:5', 'max:500'],
            'cienteLiberacao' => ['accepted'],
        ], [
            'cienteLiberacao.accepted' => 'Confirme que não há cancela ou catraca ligada ao relé.',
        ], ['motivoLiberacao' => 'motivo']);

        if ($this->chaveLiberacao === null) {
            return;
        }

        try {
            $operacao = $comandos->testarReleEmBancada($this->equipamento(), $this->chaveLiberacao, $ator, $this->motivoLiberacao);
            $processador->processar($operacao->id);
        } catch (RegraIntegracaoViolada|PayloadDivergente $e) {
            $this->erro('Liberação não enviada', $e->getMessage());

            return;
        }

        $this->operacaoLiberacaoId = $operacao->id;
        $this->confirmandoLiberacao = false;
        $situacao = $this->situacaoOperacao($operacao->refresh());
        $situacao['variant'] === 'success'
            ? $this->sucesso('Liberação: '.$situacao['rotulo'], $situacao['detalhe'])
            : $this->erro('Liberação: '.$situacao['rotulo'], $situacao['detalhe']);
    }

    // ---- Render ---------------------------------------------------------------

    public function render(RegistroAdaptadores $adaptadores): View
    {
        $ator = $this->autorizar();
        $equipamento = $this->mode === 'detail' && $this->selecionadoId ? $this->equipamento() : null;

        return view('livewire.equipamento-management', [
            'equipamentos' => $this->mode === 'list' ? Equipamento::query()->with('vinculoVigente.pontoAcesso')->orderBy('nome')->get() : collect(),
            'pontos' => $this->mode === 'form' ? PontoAcesso::query()->where('status', '!=', StatusPontoAcesso::Inativo->value)->orderBy('nome')->get() : collect(),
            'adaptadoresDisponiveis' => array_values(array_filter($adaptadores->codigos(), fn (string $c) => $adaptadores->permitido($c))),
            'equipamento' => $equipamento,
            'detalhe' => $equipamento ? $this->detalhe($equipamento) : null,
            'podeLiberar' => $ator->hasPermission(ComandosAbertura::PERMISSAO_LIBERAR),
            'testeReleHabilitado' => (bool) config('integracoes.teste_rele_habilitado'),
        ])->layout('components.layouts.app', [
            'title' => 'Equipamentos',
            'heading' => match ($this->mode) {
                'form' => 'Cadastrar equipamento',
                'detail' => $equipamento?->nome ?? 'Equipamento',
                default => 'Equipamentos',
            },
            'headingDescription' => 'Terminais de acesso da implantação: cadastro, diagnóstico e homologação em bancada',
        ]);
    }

    /** @return array<string, mixed> */
    private function detalhe(Equipamento $equipamento): array
    {
        $ultimaOperacao = OperacaoIntegracao::query()
            ->where('equipamento_id', $equipamento->id)
            ->latest('created_at')->latest('id')
            ->first();
        $liberacao = $this->operacaoLiberacaoId
            ? OperacaoIntegracao::query()->where('equipamento_id', $equipamento->id)->find($this->operacaoLiberacaoId)
            : null;

        return [
            'ponto' => $equipamento->pontoVigente(),
            'online' => $equipamento->estado_saude === EstadoSaude::Conectado,
            'saude' => $equipamento->estado_saude,
            'credencialDefinida' => $equipamento->credencialTecnicaAtiva() !== null,
            'capacidades' => $equipamento->capacidades()->orderBy('capacidade')->get(),
            'suportaImagem' => $equipamento->suporta(Capacidade::CapturarImagem),
            'suportaAbertura' => $equipamento->suporta(Capacidade::AberturaRemota),
            'ultimaOperacao' => $ultimaOperacao ? ['operacao' => $ultimaOperacao, 'situacao' => $this->situacaoOperacao($ultimaOperacao)] : null,
            'liberacao' => $liberacao ? $this->situacaoOperacao($liberacao) : null,
            'imagemDisponivel' => app(CapturaImagemEquipamento::class)->ultima($equipamento) !== null,
            'historico' => AuditoriaEvento::query()
                ->where('module', 'integracoes')
                ->where('entity_type', 'equipamentos')
                ->where('entity_id', $equipamento->id)
                ->latest('occurred_at')
                ->limit(25)
                ->get(),
            'ocorrencias' => IntegracaoOcorrencia::query()
                ->where('equipamento_id', $equipamento->id)
                ->latest('ocorrido_em')
                ->limit(10)
                ->get(),
        ];
    }

    /**
     * Estados que o operador vê: solicitado, enviado, aceito, recusado,
     * desconhecido e confirmado. "Aceito" nunca é apresentado como acesso
     * realizado.
     *
     * @return array{rotulo: string, detalhe: string, variant: string}
     */
    private function situacaoOperacao(OperacaoIntegracao $operacao): array
    {
        $mensagem = (string) $operacao->erro_sanitizado;

        return match (true) {
            $operacao->estado === EstadoOutbox::Pendente => ['rotulo' => 'Solicitado', 'detalhe' => 'Aguardando envio ao terminal.', 'variant' => 'neutral'],
            $operacao->estado === EstadoOutbox::Processando => ['rotulo' => 'Enviado', 'detalhe' => 'Enviado ao terminal, aguardando resposta.', 'variant' => 'neutral'],
            $operacao->resultado === ResultadoEquipamento::Confirmado => ['rotulo' => 'Confirmado', 'detalhe' => $this->detalheSucesso($operacao), 'variant' => 'success'],
            $operacao->resultado === ResultadoEquipamento::Aceito => ['rotulo' => 'Aceito pelo terminal', 'detalhe' => 'O terminal aceitou o comando. A abertura física não é comprovada por esta resposta e nenhum acesso foi registrado.', 'variant' => 'success'],
            $operacao->resultado === ResultadoEquipamento::Recusado => ['rotulo' => 'Recusado', 'detalhe' => $mensagem ?: 'O terminal recusou a operação.', 'variant' => 'danger'],
            $operacao->resultado === ResultadoEquipamento::ConfirmacaoDesconhecida => ['rotulo' => 'Desconhecido', 'detalhe' => ($mensagem ?: 'Sem resposta conclusiva.').' Não repita às cegas: confira o terminal.', 'variant' => 'warning'],
            $operacao->resultado === ResultadoEquipamento::Expirado => ['rotulo' => 'Expirado', 'detalhe' => 'O comando expirou antes do envio e não foi enviado.', 'variant' => 'warning'],
            $operacao->resultado === ResultadoEquipamento::CapacidadeAusente => ['rotulo' => 'Capacidade ausente', 'detalhe' => $mensagem, 'variant' => 'warning'],
            default => ['rotulo' => 'Falha', 'detalhe' => $mensagem ?: 'Falha técnica.', 'variant' => 'danger'],
        };
    }

    private function detalheSucesso(OperacaoIntegracao $operacao): string
    {
        $dados = (array) $operacao->resultado_dados;

        return match ($operacao->operacao) {
            TipoOperacao::TestarConexao => trim(sprintf('Terminal respondeu: %s, firmware %s.', $dados['modelo'] ?? 'modelo não informado', $dados['firmware'] ?? 'não informado')),
            TipoOperacao::ConsultarCapacidades => 'Capacidades consultadas: '.(implode(', ', (array) ($dados['suportadas'] ?? [])) ?: 'nenhuma homologada').'.',
            default => 'Operação confirmada pelo terminal.',
        };
    }

    // ---- Auxiliares -----------------------------------------------------------

    /** @param  callable(Equipamento, User): OperacaoIntegracao  $solicitar */
    private function executarDiagnostico(callable $solicitar, ProcessadorOperacoes $processador, string $titulo): void
    {
        $ator = $this->autorizar();

        try {
            $operacao = $solicitar($this->equipamento(), $ator);
            $processador->processar($operacao->id);
        } catch (RegraIntegracaoViolada $e) {
            $this->erro("{$titulo} não executado", $e->getMessage());

            return;
        }

        $situacao = $this->situacaoOperacao($operacao->refresh());
        $situacao['variant'] === 'success'
            ? $this->sucesso("{$titulo}: {$situacao['rotulo']}", $situacao['detalhe'])
            : $this->erro("{$titulo}: {$situacao['rotulo']}", $situacao['detalhe']);
    }

    private function pontoParaCadastro(CadastroEquipamentos $cadastro, User $ator): PontoAcesso
    {
        if (($this->form['ponto_id'] ?? '') !== '') {
            return PontoAcesso::query()->findOrFail($this->form['ponto_id']);
        }

        $padrao = (array) config('integracoes.homologacao_padrao');

        return PontoAcesso::query()->where('codigo', $padrao['ponto_codigo'])->first()
            ?? $cadastro->registrarPontoAcesso([
                'codigo' => $padrao['ponto_codigo'],
                'nome' => $padrao['ponto_nome'],
                'tipo' => TipoPontoAcesso::Bancada->value,
                'direcao_suportada' => 'bidirecional',
                'observacao' => 'Bancada de homologação, sem cancela ou catraca ligada ao relé.',
            ], $ator);
    }

    private function equipamento(?string $id = null): Equipamento
    {
        return Equipamento::query()->findOrFail($id ?? $this->selecionadoId);
    }

    private function reiniciarPainel(): void
    {
        $this->certificadoLido = null;
        $this->ultimaCaptura = null;
        $this->confirmandoLiberacao = false;
        $this->chaveLiberacao = null;
        $this->operacaoLiberacaoId = null;
        $this->trocandoSenha = false;
        $this->senhaTecnica = '';
        $this->feedback = null;
    }

    private function autorizar(): User
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof User && $usuario->hasPermission('integracoes.gerenciar'), 403);

        return $usuario;
    }

    private function autorizarLiberacao(): User
    {
        $usuario = $this->autorizar();
        abort_unless($usuario->hasPermission(ComandosAbertura::PERMISSAO_LIBERAR), 403);

        return $usuario;
    }

    private function sucesso(string $titulo, string $mensagem): void
    {
        $this->feedback = ['variant' => 'success', 'title' => $titulo, 'message' => $mensagem];
    }

    private function erro(string $titulo, string $mensagem): void
    {
        $this->feedback = ['variant' => 'danger', 'title' => $titulo, 'message' => $mensagem];
    }
}
