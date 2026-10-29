<?php

namespace App\Livewire;

use App\Integracoes\Aplicacao\CadastroEquipamentos;
use App\Integracoes\Aplicacao\CapturaImagemEquipamento;
use App\Integracoes\Aplicacao\ComandosAbertura;
use App\Integracoes\Aplicacao\DiagnosticoEquipamento;
use App\Integracoes\Aplicacao\ProcessadorOperacoes;
use App\Integracoes\Aplicacao\TesteReleBancada;
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
use Illuminate\Validation\ValidationException;
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

    public bool $editando = false;

    /** @var array<string, mixed> Formulário de edição; nunca carrega a credencial técnica. */
    public array $edicao = [];

    /** Versão lida ao abrir a edição, para detectar alteração concorrente. */
    public ?int $versaoEdicao = null;

    /** @var list<array{campo: string, rotulo: string, atual: string, novo: string}>|null */
    public ?array $previaEdicao = null;

    public bool $previaAfetaConexao = false;

    /** @var array<string, mixed>|null */
    public ?array $certificadoLido = null;

    /** @var array<string, mixed>|null */
    public ?array $ultimaCaptura = null;

    /** Facial escolhida para o teste do relé; o backend sempre relê o equipamento por este id. */
    public ?string $releEquipamentoId = null;

    public ?string $chaveLiberacao = null;

    public string $motivoLiberacao = '';

    public bool $cienteLiberacao = false;

    public ?string $operacaoLiberacaoId = null;

    public bool $registrandoHomologacaoRele = false;

    public bool $acionamentoObservado = false;

    public string $observacaoRele = '';

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

    // ---- Edição (AJ-007) -----------------------------------------------------

    private const ROTULOS_EDICAO = [
        'nome' => 'Nome',
        'endereco_rede' => 'Endereço IP',
        'esquema' => 'HTTP/HTTPS',
        'porta_rede' => 'Porta',
        'timeout_segundos' => 'Tempo limite',
        'protocolo' => 'Protocolo de integração',
        'adaptador' => 'Adaptador',
        'direcao' => 'Direção',
        'modulo_seguro_rs485' => 'Módulo seguro RS-485',
        'ponto_acesso_id' => 'Ponto de acesso',
    ];

    /** Abre a edição com os valores do servidor; a senha técnica não é carregada. */
    public function iniciarEdicao(): void
    {
        $this->autorizar();
        $equipamento = $this->equipamento();

        $this->edicao = [
            'nome' => $equipamento->nome,
            'endereco_rede' => $equipamento->endereco_rede,
            'esquema' => $equipamento->esquema,
            'porta_rede' => $equipamento->porta_rede === null ? '' : (string) $equipamento->porta_rede,
            'timeout_segundos' => $equipamento->timeout_segundos,
            'protocolo' => $equipamento->protocolo,
            'adaptador' => $equipamento->adaptador,
            'direcao' => $equipamento->direcao->value,
            'modulo_seguro_rs485' => $equipamento->modulo_seguro_rs485,
            'ponto_id' => (string) $equipamento->pontoVigente()?->id,
        ];
        $this->versaoEdicao = $equipamento->versao;
        $this->previaEdicao = null;
        $this->previaAfetaConexao = false;
        $this->trocandoSenha = false;
        $this->certificadoLido = null;
        $this->feedback = null;
        $this->resetErrorBag();
        $this->editando = true;
    }

    public function cancelarEdicao(): void
    {
        $this->editando = false;
        $this->edicao = [];
        $this->versaoEdicao = null;
        $this->previaEdicao = null;
        $this->previaAfetaConexao = false;
        $this->resetErrorBag();
    }

    /** Valida e mostra "valor atual → novo valor"; só o nome ou o tempo limite salvam direto. */
    public function revisarEdicao(CadastroEquipamentos $cadastro): void
    {
        $ator = $this->autorizar();
        $equipamento = $this->equipamento();
        $this->validarEdicao($cadastro, $equipamento);

        try {
            $previa = $cadastro->previaEdicao($equipamento, $this->dadosEdicao(), $this->edicao['ponto_id'] ?: null, (int) $this->versaoEdicao, $ator);
        } catch (RegraIntegracaoViolada $e) {
            $this->recusaEdicao($e);

            return;
        }

        if ($previa['mudancas'] === []) {
            $this->feedback = ['variant' => 'info', 'title' => 'Nada a salvar', 'message' => 'Nenhum campo foi alterado.'];

            return;
        }
        if (! $previa['homologacao']) {
            $this->confirmarEdicao($cadastro);

            return;
        }

        $this->previaEdicao = collect($previa['mudancas'])
            ->map(fn (array $valores, string $campo) => [
                'campo' => $campo,
                'rotulo' => self::ROTULOS_EDICAO[$campo] ?? $campo,
                'atual' => $this->valorEdicao($campo, $valores['old']),
                'novo' => $this->valorEdicao($campo, $valores['new']),
            ])->values()->all();
        $this->previaAfetaConexao = $previa['conexao'];
    }

    public function voltarParaEdicao(): void
    {
        $this->previaEdicao = null;
        $this->previaAfetaConexao = false;
    }

    public function confirmarEdicao(CadastroEquipamentos $cadastro): void
    {
        $ator = $this->autorizar();
        $equipamento = $this->equipamento();
        $this->validarEdicao($cadastro, $equipamento);

        try {
            $resultado = $cadastro->editarConfiguracao($equipamento, $this->dadosEdicao(), $this->edicao['ponto_id'] ?: null, (int) $this->versaoEdicao, $ator);
        } catch (RegraIntegracaoViolada $e) {
            $this->recusaEdicao($e);

            return;
        }

        $this->cancelarEdicao();
        $this->sucesso('Configurações atualizadas', match (true) {
            $resultado['conexao'] => 'Configurações atualizadas. Teste novamente a conexão e, em HTTPS, confira o certificado apresentado.',
            $resultado['homologacao'] => 'Configurações atualizadas. O terminal está em homologação; confira o funcionamento em bancada.',
            default => 'Configurações atualizadas. Conexão e homologação não foram afetadas.',
        });
    }

    private function validarEdicao(CadastroEquipamentos $cadastro, Equipamento $equipamento): void
    {
        try {
            $this->validate([
                'edicao.nome' => ['required', 'string', 'max:120'],
                'edicao.endereco_rede' => ['required', 'ip'],
                'edicao.porta_rede' => ['nullable', 'integer', 'between:1,65535'],
                'edicao.esquema' => ['required', 'in:http,https'],
                'edicao.timeout_segundos' => ['required', 'integer', 'between:1,30'],
                'edicao.protocolo' => ['required', 'string', 'max:40'],
                'edicao.adaptador' => ['required', 'string'],
                'edicao.direcao' => ['required', 'in:entrada,saida,bidirecional'],
                'edicao.ponto_id' => ['nullable', 'string'],
            ], attributes: [
                'edicao.nome' => 'nome',
                'edicao.endereco_rede' => 'endereço IP',
                'edicao.porta_rede' => 'porta',
                'edicao.timeout_segundos' => 'tempo limite',
                'edicao.protocolo' => 'protocolo de integração',
            ]);
        } catch (ValidationException $e) {
            $cadastro->registrarEdicaoRecusada($equipamento, 'dados_invalidos', array_map(
                fn (string $chave) => Str::after($chave, 'edicao.'),
                array_keys($e->errors()),
            ));

            throw $e;
        }
    }

    /** @return array<string, mixed> */
    private function dadosEdicao(): array
    {
        return array_intersect_key($this->edicao, array_flip(CadastroEquipamentos::CAMPOS_EDITAVEIS));
    }

    private function recusaEdicao(RegraIntegracaoViolada $e): void
    {
        if ($e->codigo === 'versao_desatualizada') {
            // Outro administrador salvou antes: recarrega os valores atuais.
            $this->iniciarEdicao();
            $this->erro('Equipamento alterado por outra pessoa', 'Os campos foram recarregados com os valores atuais. Revise e salve novamente.');

            return;
        }

        $this->previaEdicao = null;
        $this->erro('Equipamento não alterado', $e->getMessage());
    }

    private function valorEdicao(string $campo, mixed $valor): string
    {
        return match (true) {
            $valor === null || $valor === '' => $campo === 'porta_rede' ? 'Padrão do protocolo' : 'Não definido',
            $campo === 'esquema' => strtoupper((string) $valor),
            $campo === 'modulo_seguro_rs485' => $valor ? 'Sim' : 'Não',
            $campo === 'timeout_segundos' => $valor.' s',
            $campo === 'direcao' => ['entrada' => 'Entrada', 'saida' => 'Saída', 'bidirecional' => 'Bidirecional'][$valor] ?? (string) $valor,
            default => (string) $valor,
        };
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

    // ---- Teste individual de relés ------------------------------------------

    /** Abre a confirmação para UMA facial. A chave idempotente vale só para esta confirmação. */
    public function prepararTesteRele(string $id, TesteReleBancada $testes): void
    {
        $ator = $this->autorizarLiberacao();
        $equipamento = $this->equipamento($id);

        if (($bloqueios = $testes->bloqueios($equipamento, $ator)) !== []) {
            $this->erro('Teste do relé indisponível', reset($bloqueios));

            return;
        }

        $this->releEquipamentoId = $equipamento->id;
        $this->chaveLiberacao = 'rele:'.$equipamento->id.':'.Str::uuid7();
        $this->motivoLiberacao = '';
        $this->cienteLiberacao = false;
        $this->operacaoLiberacaoId = null;
        $this->registrandoHomologacaoRele = false;
        $this->feedback = null;
        $this->resetErrorBag();
    }

    public function cancelarTesteRele(): void
    {
        $this->releEquipamentoId = null;
        $this->chaveLiberacao = null;
        $this->operacaoLiberacaoId = null;
        $this->registrandoHomologacaoRele = false;
    }

    public function testarRele(TesteReleBancada $testes, ProcessadorOperacoes $processador): void
    {
        $ator = $this->autorizarLiberacao();

        $this->validate([
            'motivoLiberacao' => ['required', 'string', 'min:5', 'max:500'],
            'cienteLiberacao' => ['accepted'],
        ], [
            'cienteLiberacao.accepted' => 'Confirme que é um teste em bancada, sem cancela ou catraca ligada ao relé.',
        ], ['motivoLiberacao' => 'motivo']);

        if ($this->releEquipamentoId === null || $this->chaveLiberacao === null) {
            return;
        }

        try {
            // O id escolhido é resolvido de novo no escopo da implantação atual;
            // endereço, porta, certificado e credencial vêm do cadastro.
            $operacao = $testes->solicitar($this->equipamento($this->releEquipamentoId), $this->chaveLiberacao, $ator, $this->motivoLiberacao);
            $processador->processar($operacao->id);
        } catch (RegraIntegracaoViolada|PayloadDivergente $e) {
            $this->erro('Teste do relé não enviado', $e->getMessage());

            return;
        }

        $this->operacaoLiberacaoId = $operacao->id;
        $situacao = $this->situacaoOperacao($operacao->refresh());
        $situacao['variant'] === 'success'
            ? $this->sucesso('Teste do relé: '.$situacao['rotulo'], $situacao['detalhe'])
            : $this->erro('Teste do relé: '.$situacao['rotulo'], $situacao['detalhe']);
    }

    /** Nova solicitação depois de um resultado: gera outra chave para não repetir a anterior. */
    public function repetirTesteRele(TesteReleBancada $testes): void
    {
        if ($this->releEquipamentoId !== null) {
            $this->prepararTesteRele($this->releEquipamentoId, $testes);
        }
    }

    /** Abre direto o registro da homologação do relé de uma facial (sem precisar testar de novo). */
    public function abrirHomologacaoRele(string $id): void
    {
        $this->autorizarLiberacao();
        $this->releEquipamentoId = $this->equipamento($id)->id;
        $this->chaveLiberacao = null;
        $this->operacaoLiberacaoId = null;
        $this->feedback = null;
        $this->iniciarHomologacaoRele();
    }

    public function iniciarHomologacaoRele(): void
    {
        $this->autorizarLiberacao();
        $this->registrandoHomologacaoRele = true;
        $this->acionamentoObservado = false;
        $this->observacaoRele = '';
    }

    public function registrarHomologacaoRele(TesteReleBancada $testes): void
    {
        $ator = $this->autorizarLiberacao();
        if ($this->releEquipamentoId === null) {
            return;
        }

        try {
            $equipamento = $testes->registrarHomologacao($this->equipamento($this->releEquipamentoId), $ator, $this->acionamentoObservado, $this->observacaoRele);
        } catch (RegraIntegracaoViolada $e) {
            $this->erro('Relé não homologado', $e->getMessage());

            return;
        }

        $this->registrandoHomologacaoRele = false;
        $this->sucesso('Relé homologado', "Relé da porta {$equipamento->porta_rele} de \"{$equipamento->nome}\" registrado como homologado em bancada.");
    }

    // ---- Render ---------------------------------------------------------------

    public function render(RegistroAdaptadores $adaptadores): View
    {
        $ator = $this->autorizar();
        $equipamento = $this->mode === 'detail' && $this->selecionadoId ? $this->equipamento() : null;
        $testes = app(TesteReleBancada::class);
        $podeLiberar = $ator->hasPermission(ComandosAbertura::PERMISSAO_LIBERAR);
        $releSelecionado = $this->releEquipamentoId ? Equipamento::query()->find($this->releEquipamentoId) : null;
        $releOperacao = $releSelecionado && $this->operacaoLiberacaoId
            ? OperacaoIntegracao::query()->where('equipamento_id', $releSelecionado->id)->find($this->operacaoLiberacaoId)
            : null;

        return view('livewire.equipamento-management', [
            'equipamentos' => $this->mode === 'list' ? Equipamento::query()->terminais()->with('vinculoVigente.pontoAcesso')->orderBy('nome')->get() : collect(),
            'pontos' => match (true) {
                $this->mode === 'form' => PontoAcesso::query()->where('status', '!=', StatusPontoAcesso::Inativo->value)->orderBy('nome')->get(),
                // Na edição: o ponto atual ou pontos livres da implantação.
                $this->editando && $equipamento !== null => PontoAcesso::query()
                    ->where('status', '!=', StatusPontoAcesso::Inativo->value)
                    ->where(fn ($q) => $q->whereDoesntHave('vinculoVigente')->orWhere('id', $equipamento->pontoVigente()?->id))
                    ->orderBy('nome')->get(),
                default => collect(),
            },
            'adaptadoresDisponiveis' => array_values(array_filter($adaptadores->codigos(), fn (string $c) => $adaptadores->permitido($c))),
            'equipamento' => $equipamento,
            'detalhe' => $equipamento ? $this->detalhe($equipamento) : null,
            'podeLiberar' => $podeLiberar,
            'faciaisRele' => $podeLiberar && $this->mode === 'list' ? $this->faciaisParaTeste($testes, $ator) : [],
            'releSelecionado' => $releSelecionado,
            'releBloqueios' => $releSelecionado ? $testes->bloqueios($releSelecionado, $ator) : [],
            'releTestesAceitos' => $releSelecionado ? $testes->testesAceitos($releSelecionado) : 0,
            'releTestesParaHomologar' => TesteReleBancada::TESTES_PARA_HOMOLOGAR,
            'releSituacao' => $releOperacao ? $this->situacaoOperacao($releOperacao) : null,
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

        return [
            'ponto' => $equipamento->pontoVigente(),
            'online' => $equipamento->estado_saude === EstadoSaude::Conectado,
            'saude' => $equipamento->estado_saude,
            'credencialDefinida' => $equipamento->credencialTecnicaAtiva() !== null,
            'capacidades' => $equipamento->capacidades()->orderBy('capacidade')->get(),
            'suportaImagem' => $equipamento->suporta(Capacidade::CapturarImagem),
            'suportaAbertura' => $equipamento->suporta(Capacidade::AberturaRemota),
            'ultimaOperacao' => $ultimaOperacao ? ['operacao' => $ultimaOperacao, 'situacao' => $this->situacaoOperacao($ultimaOperacao)] : null,
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

    /** @return list<array{equipamento: Equipamento, bloqueios: array<string, string>, testes_aceitos: int}> */
    private function faciaisParaTeste(TesteReleBancada $testes, User $ator): array
    {
        return Equipamento::query()
            ->where('tipo', 'terminal_facial')
            ->where('status', '!=', StatusEquipamento::Inativo->value)
            ->with('vinculoVigente.pontoAcesso')
            ->orderBy('nome')
            ->get()
            ->map(fn (Equipamento $e) => ['equipamento' => $e, 'bloqueios' => $testes->bloqueios($e, $ator), 'testes_aceitos' => $testes->testesAceitos($e)])
            ->all();
    }

    private function equipamento(?string $id = null): Equipamento
    {
        return Equipamento::query()->terminais()->findOrFail($id ?? $this->selecionadoId);
    }

    private function reiniciarPainel(): void
    {
        $this->certificadoLido = null;
        $this->ultimaCaptura = null;
        $this->releEquipamentoId = null;
        $this->chaveLiberacao = null;
        $this->operacaoLiberacaoId = null;
        $this->registrandoHomologacaoRele = false;
        $this->trocandoSenha = false;
        $this->senhaTecnica = '';
        $this->cancelarEdicao();
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
