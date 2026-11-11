<?php

namespace App\Livewire\Concerns;

use App\Models\Pessoa;
use App\Models\PessoaContato;
use App\Models\PessoaDocumento;
use App\Services\AuditService;
use App\Support\NormalizadorBusca;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * Conferência e correção dos dados da pessoa na identificação da Validação
 * de Entrada. O porteiro vê os dados do cadastro e, com permissão de
 * gerenciar pessoas, corrige ali mesmo: nome, nome social, nascimento, CPF,
 * telefone e e-mail. Documento e contato anteriores são encerrados, não
 * apagados; a auditoria guarda só os nomes dos campos alterados.
 */
trait ConferenciaDadosIdentificacao
{
    public bool $editandoIdentificacao = false;

    public bool $dadosIdentificacaoSalvos = false;

    #[Locked]
    public ?int $identVersao = null;

    public string $identNome = '';

    public string $identNomeSocial = '';

    public string $identNascimento = '';

    public string $identCpf = '';

    public string $identTelefone = '';

    public string $identEmail = '';

    /** @return array{nome: string, nome_social: ?string, nascimento: ?string, cpf: ?string, telefone: ?string, email: ?string}|null */
    #[Computed]
    public function dadosIdentificacao(): ?array
    {
        $pessoa = $this->currentPessoaId ? Pessoa::query()->find($this->currentPessoaId) : null;
        if ($pessoa === null) {
            return null;
        }

        return [
            'nome' => $pessoa->nome,
            'nome_social' => $pessoa->nome_social,
            'nascimento' => $pessoa->data_nascimento?->format('d/m/Y'),
            'cpf' => $this->cpfVigente($pessoa)?->valor_apresentacao,
            'telefone' => $this->contatoVigente($pessoa, 'telefone')?->valor,
            'email' => $this->contatoVigente($pessoa, 'email')?->valor,
        ];
    }

    public function podeEditarIdentificacao(): bool
    {
        $usuario = Auth::user();

        return $this->currentPessoaId !== null
            && (bool) $usuario?->hasPermission('validacao.registrar')
            && (bool) $usuario?->hasPermission('pessoas.gerenciar');
    }

    public function editarIdentificacao(): void
    {
        abort_unless($this->podeEditarIdentificacao(), 403);

        $pessoa = Pessoa::query()->findOrFail($this->currentPessoaId);

        $this->resetErrorBag();
        $this->dadosIdentificacaoSalvos = false;
        $this->editandoIdentificacao = true;
        $this->identVersao = $pessoa->versao;
        $this->identNome = $pessoa->nome;
        $this->identNomeSocial = (string) $pessoa->nome_social;
        $this->identNascimento = (string) $pessoa->data_nascimento?->format('Y-m-d');
        $this->identCpf = (string) $this->cpfVigente($pessoa)?->valor_apresentacao;
        $this->identTelefone = (string) $this->contatoVigente($pessoa, 'telefone')?->valor;
        $this->identEmail = (string) $this->contatoVigente($pessoa, 'email')?->valor;
    }

    /** CPF ditado na correção dos dados: só aceita 11 dígitos. */
    public function preencherCpfIdentificacaoPorVoz(string $texto): void
    {
        abort_unless($this->podeEditarIdentificacao() && $this->editandoIdentificacao, 403);

        $digitos = NormalizadorBusca::digitos($texto);
        if (strlen($digitos) !== 11) {
            return; // fala incompleta não apaga o CPF atual
        }

        $this->identCpf = substr($digitos, 0, 3).'.'.substr($digitos, 3, 3).'.'.substr($digitos, 6, 3).'-'.substr($digitos, 9, 2);
        $this->resetErrorBag('identCpf');
    }

    public function cancelarEdicaoIdentificacao(): void
    {
        $this->editandoIdentificacao = false;
        $this->identVersao = null;
        $this->resetErrorBag(['identNome', 'identNomeSocial', 'identNascimento', 'identCpf', 'identTelefone', 'identEmail']);
    }

    public function salvarIdentificacao(AuditService $audit): void
    {
        abort_unless($this->podeEditarIdentificacao() && $this->editandoIdentificacao, 403);

        $this->validate([
            'identNome' => ['required', 'string', 'min:3', 'max:120'],
            'identNomeSocial' => ['nullable', 'string', 'max:120'],
            'identNascimento' => ['nullable', 'date', 'before:today'],
            'identCpf' => ['nullable', 'string', 'max:20'],
            'identTelefone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9()+\-.\s]+$/'],
            'identEmail' => ['nullable', 'email', 'max:120'],
        ], [
            'required' => 'Preencha este campo.',
            'min' => 'Informe um valor válido.',
            'before' => 'A data de nascimento precisa ser anterior a hoje.',
            'email' => 'Informe um e-mail válido.',
            'identTelefone.regex' => 'Use só números, espaços, parênteses, + ou -.',
        ]);

        $digitosTelefone = strlen(NormalizadorBusca::digitos($this->identTelefone));
        if (trim($this->identTelefone) !== '' && ($digitosTelefone < 10 || $digitosTelefone > 13)) {
            $this->addError('identTelefone', 'Informe o telefone com DDD (10 a 13 dígitos) ou deixe em branco.');

            return;
        }

        $cpfNovo = NormalizadorBusca::digitos($this->identCpf);
        if ($cpfNovo !== '' && strlen($cpfNovo) !== 11) {
            $this->addError('identCpf', 'Informe o CPF com 11 dígitos.');

            return;
        }

        $alterados = DB::transaction(function () use ($cpfNovo): ?array {
            $pessoa = Pessoa::query()->whereKey($this->currentPessoaId)->lockForUpdate()->firstOrFail();

            if ($pessoa->versao !== $this->identVersao) {
                $this->addError('identNome', 'O cadastro foi alterado por outra pessoa enquanto você editava. Cancele e abra a edição de novo.');

                return null;
            }

            $alterados = [];
            $nome = trim($this->identNome);
            $nomeSocial = trim($this->identNomeSocial) !== '' ? trim($this->identNomeSocial) : null;
            $nascimento = $this->identNascimento !== '' ? $this->identNascimento : null;

            if ($pessoa->nome !== $nome) {
                $alterados[] = 'nome';
            }
            if ($pessoa->nome_social !== $nomeSocial) {
                $alterados[] = 'nome_social';
            }
            if ($pessoa->data_nascimento?->format('Y-m-d') !== $nascimento) {
                $alterados[] = 'data_nascimento';
            }

            $cpfAtual = $this->cpfVigente($pessoa);
            if (($cpfAtual?->valor_normalizado ?? '') !== $cpfNovo) {
                if ($cpfNovo === '') {
                    $this->addError('identCpf', 'O CPF não pode ser apagado. Corrija o número se estiver errado.');

                    return null;
                }
                if (PessoaDocumento::query()->where('tipo', 'cpf')->where('valor_normalizado', $cpfNovo)->exists()) {
                    $this->addError('identCpf', 'Este CPF já está em outro cadastro. Busque a pessoa por ele em vez de corrigir este.');

                    return null;
                }
                $cpfAtual?->forceFill(['status' => 'substituido', 'ended_at' => now()])->save();
                PessoaDocumento::query()->create([
                    'pessoa_id' => $pessoa->id,
                    'tipo' => 'cpf',
                    'pais' => 'BR',
                    'valor_normalizado' => $cpfNovo,
                    'valor_apresentacao' => trim($this->identCpf),
                    'status' => 'ativo',
                    'started_at' => now(),
                ]);
                $alterados[] = 'cpf';
            }

            foreach (['telefone' => trim($this->identTelefone), 'email' => trim($this->identEmail)] as $tipo => $valor) {
                if ((string) $this->contatoVigente($pessoa, $tipo)?->valor !== $valor) {
                    $this->trocarContatoIdentificacao($pessoa, $tipo, $valor);
                    $alterados[] = $tipo;
                }
            }

            if ($alterados !== []) {
                $pessoa->forceFill([
                    'nome' => $nome,
                    'nome_social' => $nomeSocial,
                    'data_nascimento' => $nascimento,
                    'versao' => $pessoa->versao + 1,
                    'updated_by' => Auth::id(),
                ])->save();
            }

            return $alterados;
        });

        if ($alterados === null) {
            return;
        }

        if ($alterados !== []) {
            $audit->record(
                action: 'pessoa_atualizada',
                module: 'pessoas',
                entityType: 'pessoas',
                entityId: $this->currentPessoaId,
                // Só os nomes dos campos: nada de valor pessoal na auditoria.
                metadata: ['campos' => $alterados, 'origem' => 'validacao_identificacao'],
            );
        }

        $this->editandoIdentificacao = false;
        $this->identVersao = null;
        $this->dadosIdentificacaoSalvos = $alterados !== [];
        unset($this->currentPerson, $this->dadosIdentificacao);
    }

    private function encerrarEdicaoIdentificacao(): void
    {
        $this->cancelarEdicaoIdentificacao();
        $this->dadosIdentificacaoSalvos = false;
    }

    private function cpfVigente(Pessoa $pessoa): ?PessoaDocumento
    {
        return PessoaDocumento::query()
            ->where('pessoa_id', $pessoa->id)
            ->where('tipo', 'cpf')
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();
    }

    private function contatoVigente(Pessoa $pessoa, string $tipo): ?PessoaContato
    {
        return PessoaContato::query()
            ->where('pessoa_id', $pessoa->id)
            ->where('tipo', $tipo)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();
    }

    /** O contato anterior é encerrado, não apagado. */
    private function trocarContatoIdentificacao(Pessoa $pessoa, string $tipo, string $valor): void
    {
        PessoaContato::query()->where('pessoa_id', $pessoa->id)->where('tipo', $tipo)->whereNull('ended_at')
            ->update(['ended_at' => now()]);

        if ($valor !== '') {
            PessoaContato::query()->create([
                'pessoa_id' => $pessoa->id,
                'tipo' => $tipo,
                'valor' => $valor,
                'principal' => $tipo === 'telefone',
                'verificado' => false,
                'started_at' => now(),
            ]);
        }
    }
}
