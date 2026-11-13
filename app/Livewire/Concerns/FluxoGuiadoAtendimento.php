<?php

namespace App\Livewire\Concerns;

use App\Acesso\Aplicacao\AutorizacoesAcesso;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\AutorizacaoAcesso;
use App\Models\Imovel;
use App\Models\Pessoa;
use App\Models\User;
use App\Services\ContribuicoesAtendimento;
use App\Services\PerfilAtendimento;
use App\Services\PrivateFileService;
use App\Services\ValidadorFotoPessoa;
use App\Support\NormalizadorBusca;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    /** Concluir: enviar o cadastro rápido também ao terminal facial (com permissão). */
    public bool $enviarFacial = false;

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
            $this->descartarCapturaPlaca();
        }
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
            $this->salvarCadastroRapido(enviar: $this->enviarFacial);
            if ($this->quickRegistrationOpen) {
                // Não gravou (conflito de CPF, imóvel, terminal...): o erro está no campo.
                $this->passo = $this->getErrorBag()->hasAny(['fotoPessoa']) ? 'foto' : ($this->enviarFacial && $this->getErrorBag()->hasAny(['quickTerminalId', 'quickTerminalConfirmado']) ? 'concluir' : 'dados');

                return;
            }
        } elseif ($this->fotoIdentificacao !== null) {
            $this->salvarFotoIdentificacao(app(PrivateFileService::class), app(ValidadorFotoPessoa::class));
            if ($this->getErrorBag()->has('fotoIdentificacao')) {
                $this->passo = 'foto';

                return;
            }
        }

        if ($decisao === 'liberar' && ! $this->gravarTipoNoCadastro()) {
            $this->passo = 'dados';

            return;
        }

        match ($decisao) {
            'negar' => $this->deny(),
            'pendente' => $this->savePending(),
            'liberar' => $this->release(),
        };
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
            'veiculo' => $this->veioDeCarro ? NormalizadorBusca::documento($this->placaInformada) : 'Veio a pé',
            'pagamento' => $pagamento,
            'autorizacao' => $this->resumoAutorizacao(),
        ];
    }

    /** Linha "Autorização" do Concluir: o que o Liberar entrada vai gravar no cadastro. */
    private function resumoAutorizacao(): ?string
    {
        if ($this->quickRegistrationOpen || $this->quickPersonRegistered || $this->currentPessoaId === null
            || ! isset(self::TIPOS_ATENDIMENTO[$this->tipoAtendimento]) || $this->protocol !== null || $this->tipoDoCadastro()['travado']) {
            return null;
        }

        if ($this->tipoAtendimento === 'turista') {
            return 'Turista por 24 h, gravada ao liberar a entrada';
        }

        try {
            $fim = CarbonImmutable::parse($this->validoAte)->format('d/m/Y H:i');
        } catch (Throwable) {
            $fim = '—';
        }

        return self::TIPOS_ATENDIMENTO[$this->tipoAtendimento].' até '.$fim.', gravada ao liberar a entrada';
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

    /** Liberar entrada: o tipo escolhido vira autorização no cadastro. Falha volta à etapa Dados. */
    private function gravarTipoNoCadastro(): bool
    {
        if ($this->quickRegistrationOpen || $this->quickPersonRegistered || $this->currentPessoaId === null
            || ! isset(self::TIPOS_ATENDIMENTO[$this->tipoAtendimento]) || $this->tipoDoCadastro()['travado']) {
            return true;
        }

        $pessoa = Pessoa::query()->findOrFail($this->currentPessoaId);
        $usuario = Auth::user();
        $usuario = $usuario instanceof User ? $usuario : null;
        $autorizacoes = app(AutorizacoesAcesso::class);

        try {
            if ($this->tipoAtendimento === 'turista') {
                $autorizacoes->registrarVisitaTurista($pessoa, null, '', null, null, $usuario);
            } else {
                $imovel = $this->tipoAtendimento === 'visitante'
                    ? Imovel::query()->where('codigo', strtoupper(trim($this->imovelAtendimento)))->first()
                    : null;
                $autorizacoes->registrarAutorizacaoComPrazo(
                    $pessoa,
                    $this->tipoAtendimento,
                    $imovel,
                    $this->tipoAtendimento === 'visitante' ? $this->responsavelAtendimento : '',
                    CarbonImmutable::parse($this->validoAte),
                    $usuario,
                );
            }
        } catch (RegraIntegracaoViolada $e) {
            $this->addError($this->tipoAtendimento === 'turista' ? 'tipoAtendimento' : 'validoAte', $e->getMessage());

            return false;
        }

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
        $this->enviarFacial = false;
        $this->reiniciarTipoAtendimento();
    }
}
