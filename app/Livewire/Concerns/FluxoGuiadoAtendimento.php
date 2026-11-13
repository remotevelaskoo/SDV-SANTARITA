<?php

namespace App\Livewire\Concerns;

use App\Acesso\Aplicacao\AutorizacoesAcesso;
use App\Acesso\Aplicacao\EnvioFacial;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\AutorizacaoAcesso;
use App\Models\Imovel;
use App\Models\Pessoa;
use App\Models\User;
use App\Models\Vinculo;
use App\Services\ContribuicoesAtendimento;
use App\Services\PerfilAtendimento;
use App\Services\PrivateFileService;
use App\Services\ValidadorFotoPessoa;
use App\Services\VeiculoDoAtendimento;
use App\Support\NormalizadorBusca;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Throwable;

/**
 * Atendimento guiado da Validação de entrada (pedido do PO de 12/11/2026):
 * Dados → Foto → Placa → Pagamento → Concluir, uma etapa por vez.
 *
 * - vale para todo atendimento: quem é achado na busca segue sem digitar
 *   dados, e a etapa Foto só aparece se a pessoa ainda não tem foto;
 * - Pagamento só aparece para turista (regra de 11/11/2026);
 * - nada é gravado antes do Concluir: o cadastro rápido, a foto e o
 *   atendimento são gravados juntos ao escolher Negar, Salvar sem liberar ou
 *   Liberar. Cancelar no meio não deixa cadastro pela metade;
 * - a foto é obrigatória para concluir (tirada agora ou a que a pessoa já tem).
 *
 * As regras de cada etapa continuam nos métodos já existentes (dados
 * conferidos, validação da foto, placa, contribuição e ticket, decisão).
 */
trait FluxoGuiadoAtendimento
{
    /** Etapa visível agora. */
    public string $passo = 'dados';

    /** Etapa Placa: null = ainda não respondeu; false = veio a pé. */
    public ?bool $veioDeCarro = null;

    /** Etapa Placa: dados do veículo (opcionais), gravados no cadastro da pessoa ao concluir. */
    public string $veiculoMarca = '';

    public string $veiculoModelo = '';

    public string $veiculoCor = '';

    /** Autorização gravada (ou vigente) neste "Liberar entrada", enviada ao terminal facial. */
    #[Locked]
    public ?string $autorizacaoLiberada = null;

    /** "Liberar entrada" com acesso: autorização gravada/vigente ou vínculo com imóvel. */
    #[Locked]
    public bool $liberacaoComAcesso = false;

    /** "Liberar entrada" sem nenhum acesso possível (morador sem imóvel): fica pendente. */
    #[Locked]
    public bool $semAcessoNaLiberacao = false;

    /** Resultado do envio ao terminal facial no "Liberar entrada" (aparece junto da decisão). */
    #[Locked]
    public ?array $envioFacialLiberacao = null;

    /**
     * Pessoa já cadastrada e sem vínculo (pedido do PO de 13/11/2026): o
     * porteiro escolhe o tipo na etapa Dados. No Liberar entrada o tipo vira
     * autorização no cadastro: turista sempre 24 h; Visitante, Prestador e
     * Outro até a data e hora escolhidas (no máximo 30 dias).
     */
    public string $tipoAtendimento = '';

    /** Fim da autorização com prazo (datetime-local), começa em agora + 24 h. */
    public string $validoAte = '';

    /** Visitante: imóvel visitado e responsável (obrigatórios, docs/021 §14). */
    public string $imovelAtendimento = '';

    public string $responsavelAtendimento = '';

    public const TIPOS_ATENDIMENTO = [
        'turista' => 'Turista',
        'visitante' => 'Visitante',
        'prestador' => 'Prestador',
        'outro' => 'Outro',
    ];

    public const PASSOS = [
        'dados' => 'Dados',
        'foto' => 'Foto',
        'placa' => 'Placa',
        'pagamento' => 'Pagamento',
        'concluir' => 'Concluir',
    ];

    /** @return array<string, string> etapas deste atendimento, na ordem */
    public function passosDoAtendimento(): array
    {
        return array_filter(self::PASSOS, fn (string $passo) => match ($passo) {
            'foto' => $this->precisaEtapaFoto(),
            'pagamento' => $this->perfilAtual()['turista'],
            default => true,
        }, ARRAY_FILTER_USE_KEY);
    }

    public function continuar(): void
    {
        if (! $this->validarPasso($this->passo)) {
            return;
        }

        $passos = array_keys($this->passosDoAtendimento());
        $indice = array_search($this->passo, $passos, true);
        $this->passo = $passos[$indice === false ? 0 : min($indice + 1, count($passos) - 1)];
    }

    public function voltar(): void
    {
        $passos = array_keys($this->passosDoAtendimento());
        $indice = array_search($this->passo, $passos, true);
        $this->passo = $passos[$indice === false || $indice === 0 ? 0 : $indice - 1];
        $this->resetErrorBag();
    }

    /** Clique numa etapa da barra de progresso: só para trás (as da frente exigem o Continuar). */
    public function irParaPasso(string $passo): void
    {
        $passos = array_keys($this->passosDoAtendimento());
        $destino = array_search($passo, $passos, true);
        $atual = array_search($this->passo, $passos, true);
        if ($destino !== false && ($atual === false || $destino < $atual) && $this->protocol === null) {
            $this->passo = $passo;
            $this->resetErrorBag();
        }
    }

    public function updatedVeioDeCarro(): void
    {
        $this->resetErrorBag(['veioDeCarro', 'placaInformada']);
        if ($this->veioDeCarro === false) {
            $this->placaInformada = '';
            $this->limparDadosDoVeiculo();
            $this->descartarCapturaPlaca();
        }
    }

    /** Cartão de "Veículos no cadastro": usa a placa e traz marca, modelo e cor. */
    public function usarVeiculoDoCadastro(string $placa): void
    {
        $this->placaInformada = NormalizadorBusca::documento($placa);
        $this->limparDadosDoVeiculo();
        $this->preencherDadosDoVeiculo();
    }

    /** Placa já cadastrada: completa os campos vazios com o que o cadastro tem. */
    private function preencherDadosDoVeiculo(): void
    {
        $veiculo = VeiculoDoAtendimento::doCadastro($this->placaInformada);
        if ($veiculo === null) {
            return;
        }
        $this->veiculoMarca = $this->veiculoMarca !== '' ? $this->veiculoMarca : (string) $veiculo->brand;
        $this->veiculoModelo = $this->veiculoModelo !== '' ? $this->veiculoModelo : (string) $veiculo->model;
        $this->veiculoCor = $this->veiculoCor !== '' ? $this->veiculoCor : (string) $veiculo->color;
    }

    private function limparDadosDoVeiculo(): void
    {
        $this->veiculoMarca = '';
        $this->veiculoModelo = '';
        $this->veiculoCor = '';
    }

    /** @return list<string> */
    public function modelosSugeridos(): array
    {
        $marcas = (array) config('veiculos.marcas');
        $marca = collect(array_keys($marcas))->first(fn (string $m) => mb_strtolower($m) === mb_strtolower(trim($this->veiculoMarca)));

        return $marca !== null ? $marcas[$marca] : [];
    }

    /** O que o cadastro já tem para esta placa (para marcar em verde). */
    public function veiculoDoCadastroAtual(): ?array
    {
        $veiculo = VeiculoDoAtendimento::doCadastro($this->placaInformada);

        return $veiculo === null ? null : ['marca' => (string) $veiculo->brand, 'modelo' => (string) $veiculo->model, 'cor' => (string) $veiculo->color];
    }

    /**
     * Última etapa: confere tudo de novo, grava cadastro e foto e registra a
     * decisão. Erro em qualquer etapa leva o operador de volta a ela.
     */
    public function concluir(string $decisao): void
    {
        if (! in_array($decisao, ['negar', 'pendente', 'liberar'], true) || $this->atendimentoJaRegistrado()) {
            return;
        }

        foreach (array_keys($this->passosDoAtendimento()) as $passo) {
            if ($passo !== 'concluir' && ! $this->validarPasso($passo)) {
                return;
            }
        }

        if ($decisao === 'negar') {
            $this->validate([
                'denialReason' => ['required', Rule::in($this->denialReasonCodigos())],
                'denialDetails' => ['nullable', 'string', 'max:200'],
            ]);
        }

        if ($this->quickRegistrationOpen) {
            // Regra do PO (13/11/2026): o envio ao terminal facial é só do "Liberar entrada",
            // depois de gravar a autorização; salvar o cadastro não envia nada.
            $this->salvarCadastroRapido(enviar: false);
            if ($this->quickRegistrationOpen) {
                // Não gravou (conflito de CPF, imóvel...): o erro está no campo.
                $this->passo = $this->getErrorBag()->hasAny(['fotoPessoa']) ? 'foto' : 'dados';

                return;
            }
            // Se a autorização falhar depois, a pessoa já existe: a etapa Dados mostra o tipo para corrigir.
            $this->tipoAtendimento = $this->quickAccessType;
        } elseif ($this->fotoIdentificacao !== null) {
            $this->salvarFotoIdentificacao(app(PrivateFileService::class), app(ValidadorFotoPessoa::class));
            if ($this->getErrorBag()->has('fotoIdentificacao')) {
                $this->passo = 'foto';

                return;
            }
        }

        if ($decisao === 'liberar' && ! $this->gravarAutorizacaoDaLiberacao()) {
            $this->passo = 'dados';

            return;
        }

        match ($decisao) {
            'negar' => $this->deny(),
            'pendente' => $this->savePending(),
            'liberar' => $this->release(),
        };

        if ($decisao === 'liberar' && $this->liberacaoComAcesso && $this->protocol !== null) {
            $this->enviarAoFacial();
        }
    }

    /**
     * "Liberar entrada" (regra do PO, 13/11/2026): grava a autorização com o
     * tempo do perfil e envia a pessoa ao terminal facial do posto. O SDV não
     * abre nada: a pessoa passa na facial, e o terminal faz a liberação.
     * Sem permissão, sem terminal ou com terminal não homologado, a
     * autorização fica gravada e o aviso diz que o envio está pendente.
     */
    private function enviarAoFacial(): void
    {
        $autorizacao = $this->autorizacaoLiberada !== null ? AutorizacaoAcesso::query()->find($this->autorizacaoLiberada) : null;
        $this->autorizacaoLiberada = null;
        $usuario = Auth::user();
        $pessoa = $this->currentPessoaId !== null ? Pessoa::query()->find($this->currentPessoaId) : null;
        if ($pessoa === null || ! $usuario instanceof User) {
            return;
        }

        $validade = $autorizacao !== null
            ? self::TIPOS_ATENDIMENTO[$this->tipoDaAutorizacao($autorizacao)].': acesso até '.$autorizacao->fim->format('d/m/Y H:i').'.'
            : 'Acesso pelo vínculo com o imóvel.';
        if (! $usuario->hasPermission('facial.enviar')) {
            $this->envioFacialLiberacao = ['variant' => 'warning', 'title' => 'Envio ao terminal facial pendente', 'message' => $validade.' Seu perfil não envia à facial: peça a quem tem a permissão.'];

            return;
        }

        $envio = app(EnvioFacial::class);
        $terminais = $envio->terminaisPara($usuario);
        $doPosto = $terminais->where('do_posto', true);
        $terminal = $doPosto->count() === 1 ? $doPosto->first() : ($terminais->count() === 1 ? $terminais->first() : null);
        if ($terminal === null) {
            $this->envioFacialLiberacao = ['variant' => 'warning', 'title' => 'Envio ao terminal facial pendente', 'message' => $validade.($terminais->isEmpty()
                ? ' Não há terminal facial ativo no seu posto.'
                : ' Há mais de um terminal possível; envie pela ficha da pessoa ou peça ao administrador para ligar um terminal ao posto.')];

            return;
        }

        $resultado = $envio->enviar($pessoa, $terminal['id'], $usuario, $autorizacao)->paraTela();
        $this->envioFacialLiberacao = [
            'variant' => $resultado['variant'],
            'title' => $resultado['title'].' · '.$terminal['nome'],
            'message' => $validade.' '.$resultado['message'].($resultado['variant'] === 'success' ? ' A pessoa já pode passar na facial.' : ''),
        ];
    }

    private function tipoDaAutorizacao(AutorizacaoAcesso $autorizacao): string
    {
        return $autorizacao->tipo === AutorizacaoAcesso::TIPO_TURISTA ? 'turista' : (isset(self::TIPOS_ATENDIMENTO[$autorizacao->tipo]) ? $autorizacao->tipo : 'outro');
    }

    /**
     * Resumo do Concluir.
     *
     * @return array{pessoa: string, tipo: string, novo: bool, foto: string, veiculo: string, pagamento: ?string, autorizacao: ?string}
     */
    public function resumoAtendimento(): array
    {
        $novo = $this->quickRegistrationOpen;
        $perfil = $this->perfilAtual();
        $foto = match (true) {
            $novo && $this->fotoPessoa !== null, ! $novo && $this->fotoIdentificacao !== null => 'Tirada agora',
            $novo && $this->selfieDoPreCadastro() !== null => 'Selfie do pré-cadastro',
            default => 'Já tem foto no cadastro',
        };

        $pagamento = null;
        if ($perfil['turista']) {
            $valor = 'R$ '.number_format((float) ContribuicoesAtendimento::valorPadrao(), 2, ',', '.');
            $pagamento = match ($this->contribution) {
                'yes' => "Contribui {$valor} · ".['dinheiro' => 'Dinheiro', 'pix' => 'PIX', 'cartao' => 'Cartão'][$this->paymentMethod].' · ticket '.$this->ticketNumero,
                'no' => 'Não contribui',
                default => 'Isento',
            };
        }

        return [
            'pessoa' => $novo ? $this->quickName : ($this->currentPerson['name'] ?? '—'),
            'tipo' => $novo ? ucfirst($this->quickAccessType) : $perfil['perfil'],
            'novo' => $novo,
            'foto' => $foto,
            'veiculo' => $this->veioDeCarro
                ? collect([NormalizadorBusca::documento($this->placaInformada), trim($this->veiculoMarca), trim($this->veiculoModelo), trim($this->veiculoCor)])->filter()->join(' · ')
                : 'Veio a pé',
            'pagamento' => $pagamento,
            'autorizacao' => $this->resumoAutorizacao(),
        ];
    }

    /** Linha "Autorização" do Concluir: o que o "Liberar entrada" grava e envia ao terminal facial. */
    private function resumoAutorizacao(): ?string
    {
        if ($this->protocol !== null || ($this->currentPessoaId === null && ! $this->quickRegistrationOpen)) {
            return null;
        }

        if (! $this->quickRegistrationOpen && $this->tipoDoCadastro()['travado']) {
            $detalhe = $this->tipoDoCadastro()['detalhe'];

            return 'Já autorizado'.($detalhe ? ' · '.$detalhe : '').' Liberar entrada envia ao terminal facial.';
        }

        $tipo = $this->quickRegistrationOpen ? $this->quickAccessType : $this->tipoAtendimento;
        if ($tipo === 'morador') {
            return trim($this->quickPropertyCode) !== ''
                ? 'Morador pelo vínculo com o imóvel '.strtoupper(trim($this->quickPropertyCode)).'. Liberar entrada envia ao terminal facial.'
                : 'Morador sem imóvel: sem autorização. Informe o imóvel para poder liberar.';
        }
        if (! isset(self::TIPOS_ATENDIMENTO[$tipo])) {
            return null;
        }
        if ($tipo === 'turista') {
            return 'Turista por 24 h, gravada e enviada ao terminal facial ao Liberar entrada';
        }

        try {
            $fim = CarbonImmutable::parse($this->validoAte)->format('d/m/Y H:i');
        } catch (Throwable) {
            $fim = '—';
        }

        return self::TIPOS_ATENDIMENTO[$tipo].' até '.$fim.', gravada e enviada ao terminal facial ao Liberar entrada';
    }

    /**
     * Etapa Foto: cadastro novo sempre. Pessoa achada na busca também passa
     * por ela (pedido do PO de 13/11/2026): a foto que já tem aparece em
     * verde e o porteiro pode tirar outra.
     */
    private function precisaEtapaFoto(): bool
    {
        if ($this->quickRegistrationOpen) {
            return true;
        }

        return $this->currentPessoaId !== null
            && ($this->currentPerson['photo_url'] !== null || $this->currentPerson['can_take_photo'] || $this->fotoIdentificacao !== null);
    }

    /**
     * Tipo de acesso da pessoa já cadastrada.
     * Com vínculo ou autorização vigente o tipo vem do cadastro e fica travado;
     * sem nada disso o porteiro escolhe.
     *
     * @return array{travado: bool, rotulo: string, detalhe: ?string}
     */
    public function tipoDoCadastro(): array
    {
        $perfil = app(PerfilAtendimento::class)->daPessoa($this->currentPessoaId, now());
        if ($perfil['perfil'] === 'Sem vínculo') {
            return ['travado' => false, 'rotulo' => 'Sem vínculo', 'detalhe' => null];
        }

        $autorizacao = $this->currentPessoaId === null ? null : AutorizacaoAcesso::query()
            ->where('pessoa_id', $this->currentPessoaId)
            ->whereIn('tipo', [AutorizacaoAcesso::TIPO_TURISTA, ...AutorizacaoAcesso::TIPOS_COM_PRAZO])
            ->where('status', 'ativa')
            ->where('fim', '>', now())
            ->latest('inicio')
            ->first();

        return [
            'travado' => true,
            'rotulo' => $perfil['perfil'],
            'detalhe' => $autorizacao !== null
                ? 'Autorização vigente até '.$autorizacao->fim->format('d/m/Y H:i').'.'
                : 'Definido pelo vínculo com o imóvel. Para mudar, altere o vínculo no cadastro da pessoa.',
        ];
    }

    public function updatedTipoAtendimento(): void
    {
        $this->resetErrorBag(['tipoAtendimento', 'validoAte', 'imovelAtendimento', 'responsavelAtendimento']);
        if ($this->tipoAtendimento !== 'visitante') {
            $this->imovelAtendimento = '';
            $this->responsavelAtendimento = '';
        }
    }

    /**
     * "Liberar entrada" (regra do PO, 13/11/2026), para cadastro novo e já existente:
     * turista ganha 24 h; visitante, prestador e outro ganham a autorização até o
     * "Válido até"; morador entra pelo vínculo com o imóvel; quem já tem vínculo ou
     * autorização vigente usa a que tem. Falha volta à etapa Dados.
     */
    private function gravarAutorizacaoDaLiberacao(): bool
    {
        $this->autorizacaoLiberada = null;
        $this->liberacaoComAcesso = false;
        $this->semAcessoNaLiberacao = false;
        if ($this->currentPessoaId === null) {
            return true;
        }

        $pessoa = Pessoa::query()->findOrFail($this->currentPessoaId);
        $novo = $this->quickPersonRegistered;
        $tipo = $novo ? $this->quickAccessType : $this->tipoAtendimento;
        $autorizacoes = app(AutorizacoesAcesso::class);
        $vigente = $autorizacoes->vigentePara($pessoa);

        // Já cadastrado com vínculo ou autorização vigente: o tipo vem do cadastro.
        if (! $novo && $this->tipoDoCadastro()['travado']) {
            $this->autorizacaoLiberada = $vigente?->id;
            $this->liberacaoComAcesso = true;

            return true;
        }

        if ($tipo === 'morador') {
            $this->liberacaoComAcesso = Vinculo::query()->where('pessoa_id', $pessoa->id)->where('status', 'ativo')
                ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', now()))->exists();
            $this->semAcessoNaLiberacao = ! $this->liberacaoComAcesso;

            return true;
        }

        if (! isset(self::TIPOS_ATENDIMENTO[$tipo])) {
            return true;
        }

        // CPF reaproveitado que já tem visita ou prazo vigente: usa a que tem.
        if ($vigente !== null && in_array($vigente->tipo, [AutorizacaoAcesso::TIPO_TURISTA, ...AutorizacaoAcesso::TIPOS_COM_PRAZO], true)) {
            $this->autorizacaoLiberada = $vigente->id;
            $this->liberacaoComAcesso = true;

            return true;
        }

        $usuario = Auth::user();
        $usuario = $usuario instanceof User ? $usuario : null;
        $codigoImovel = $novo ? $this->quickPropertyCode : $this->imovelAtendimento;
        $responsavel = $novo ? $this->quickResponsible : $this->responsavelAtendimento;

        try {
            if ($tipo === 'turista') {
                $autorizacao = $autorizacoes->registrarVisitaTurista($pessoa, null, '', null, null, $usuario);
            } else {
                $imovel = trim($codigoImovel) !== '' ? Imovel::query()->where('codigo', strtoupper(trim($codigoImovel)))->first() : null;
                $autorizacao = $autorizacoes->registrarAutorizacaoComPrazo(
                    $pessoa,
                    $tipo,
                    $tipo === 'visitante' ? $imovel : null,
                    $tipo === 'visitante' ? $responsavel : '',
                    CarbonImmutable::parse($this->validoAte),
                    $usuario,
                );
            }
        } catch (RegraIntegracaoViolada $e) {
            $this->addError($tipo === 'turista' ? 'tipoAtendimento' : 'validoAte', $e->getMessage());

            return false;
        }

        $this->autorizacaoLiberada = $autorizacao->id;
        $this->liberacaoComAcesso = true;

        return true;
    }

    private function reiniciarTipoAtendimento(): void
    {
        $this->tipoAtendimento = '';
        $this->validoAte = now()->addDay()->format('Y-m-d\TH:i');
        $this->imovelAtendimento = '';
        $this->responsavelAtendimento = '';
    }

    /** Etapa Dados de quem já tem cadastro e não tem vínculo: tipo escolhido e prazo válido. */
    private function validarTipoAtendimento(): bool
    {
        $this->resetErrorBag(['tipoAtendimento', 'validoAte', 'imovelAtendimento', 'responsavelAtendimento']);
        if ($this->tipoDoCadastro()['travado']) {
            return true;
        }

        if (! isset(self::TIPOS_ATENDIMENTO[$this->tipoAtendimento])) {
            $this->addError('tipoAtendimento', 'Escolha o tipo de acesso desta pessoa.');

            return false;
        }
        if ($this->tipoAtendimento === 'turista') {
            return true;
        }

        $ok = true;
        try {
            $fim = $this->validoAte === '' ? null : CarbonImmutable::parse($this->validoAte);
        } catch (Throwable) {
            $fim = null;
        }
        if ($fim === null) {
            $this->addError('validoAte', 'Informe até quando vale a autorização.');
            $ok = false;
        } elseif ($fim->lessThanOrEqualTo(now())) {
            $this->addError('validoAte', 'A data final precisa ser depois de agora.');
            $ok = false;
        } elseif ($fim->greaterThan(now()->addDays(AutorizacaoAcesso::PRAZO_MAXIMO_DIAS))) {
            $this->addError('validoAte', 'A autorização pode durar no máximo '.AutorizacaoAcesso::PRAZO_MAXIMO_DIAS.' dias.');
            $ok = false;
        }

        if ($this->tipoAtendimento === 'visitante') {
            if (trim($this->imovelAtendimento) === '') {
                $this->addError('imovelAtendimento', 'Informe o imóvel que o visitante vai visitar.');
                $ok = false;
            } elseif (! Imovel::query()->where('codigo', strtoupper(trim($this->imovelAtendimento)))->exists()) {
                $this->addError('imovelAtendimento', 'Imóvel não encontrado com este código.');
                $ok = false;
            }
            if (trim($this->responsavelAtendimento) === '') {
                $this->addError('responsavelAtendimento', 'Informe o responsável pelo visitante.');
                $ok = false;
            } elseif (mb_strlen($this->responsavelAtendimento) > 120) {
                $this->addError('responsavelAtendimento', 'Use até 120 caracteres.');
                $ok = false;
            }
        }

        return $ok;
    }

    private function validarPasso(string $passo): bool
    {
        try {
            $ok = match ($passo) {
                'dados' => $this->validarDados(),
                'foto' => $this->validarFoto(),
                'placa' => $this->validarPlaca(),
                'pagamento' => $this->validarPagamento(),
                default => true,
            };
        } catch (ValidationException $e) {
            $this->passo = $passo;

            throw $e;
        }

        if (! $ok) {
            $this->passo = $passo;
        }

        return $ok;
    }

    private function validarDados(): bool
    {
        if ($this->quickRegistrationOpen) {
            // Ainda na escolha "fotografar ou digitar": segue para digitar os dados.
            if ($this->quickEtapa === 'documento') {
                $this->quickEtapa = 'conferencia';

                return false;
            }

            return $this->dadosConferidos();
        }

        if ($this->currentPessoaId === null) {
            $this->addError('busca', 'Busque e selecione a pessoa, ou faça o cadastro rápido.');

            return false;
        }

        return $this->quickPersonRegistered || $this->validarTipoAtendimento();
    }

    private function validarFoto(): bool
    {
        if ($this->quickRegistrationOpen) {
            $existente = $this->quickPessoaExistenteId !== null ? $this->cadastroExistenteResumo() : null;
            if ($this->fotoPessoa !== null || $this->selfieDoPreCadastro() !== null || ($existente['tem_foto'] ?? false)) {
                return ! $this->getErrorBag()->has('fotoPessoa');
            }
            $this->addError('fotoPessoa', 'Tire a foto da pessoa para continuar.');

            return false;
        }

        if ($this->precisaEtapaFoto() && $this->fotoIdentificacao === null && ! $this->currentPerson['has_photo']) {
            $this->addError('fotoIdentificacao', 'Tire a foto da pessoa para continuar.');

            return false;
        }

        return ! $this->getErrorBag()->has('fotoIdentificacao');
    }

    private function validarPlaca(): bool
    {
        if ($this->veioDeCarro === null) {
            $this->addError('veioDeCarro', 'Responda se a pessoa veio de carro.');

            return false;
        }
        if ($this->veioDeCarro === false) {
            return true;
        }

        $placa = NormalizadorBusca::documento($this->placaInformada);
        if ($placa === '') {
            $this->addError('placaInformada', 'Digite a placa do veículo.');

            return false;
        }
        if (! preg_match(self::PLACA_REGEX, $placa)) {
            $this->addError('placaInformada', 'Placa inválida. Use o formato ABC1234 ou ABC1D23.');

            return false;
        }

        return true;
    }

    private function validarPagamento(): bool
    {
        $this->validateCommonFields();

        return true;
    }

    private function reiniciarFluxoGuiado(): void
    {
        $this->passo = 'dados';
        $this->veioDeCarro = null;
        $this->limparDadosDoVeiculo();
        $this->autorizacaoLiberada = null;
        $this->liberacaoComAcesso = false;
        $this->semAcessoNaLiberacao = false;
        $this->envioFacialLiberacao = null;
        $this->reiniciarTipoAtendimento();
    }
}
