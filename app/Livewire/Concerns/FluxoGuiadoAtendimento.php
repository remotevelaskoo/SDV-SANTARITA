<?php

namespace App\Livewire\Concerns;

use App\Services\ContribuicoesAtendimento;
use App\Services\PrivateFileService;
use App\Services\ValidadorFotoPessoa;
use App\Support\NormalizadorBusca;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

        match ($decisao) {
            'negar' => $this->deny(),
            'pendente' => $this->savePending(),
            'liberar' => $this->release(),
        };
    }

    /**
     * Resumo do Concluir.
     *
     * @return array{pessoa: string, tipo: string, novo: bool, foto: string, veiculo: string, pagamento: ?string}
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
        ];
    }

    /** Etapa Foto: cadastro novo sempre; pessoa achada na busca só se ainda não tem foto. */
    private function precisaEtapaFoto(): bool
    {
        if ($this->quickRegistrationOpen) {
            return true;
        }

        return $this->currentPessoaId !== null && ($this->currentPerson['can_take_photo'] || $this->fotoIdentificacao !== null);
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

        return true;
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

        if ($this->precisaEtapaFoto() && $this->fotoIdentificacao === null) {
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
    }
}
