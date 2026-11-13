<?php

namespace App\Services\PreCadastro;

use App\Models\AutorizacaoAcesso;
use App\Models\Pessoa;
use App\Models\PessoaContato;
use App\Models\PessoaDocumento;
use App\Models\PreRegistration;
use App\Services\AuditService;
use App\Services\PrivateFileService;
use App\Services\ValidadorFotoPessoa;
use App\Support\NormalizadorBusca;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Pré-cadastro aprovado vira pessoa no SDV (pedido do PO de 13/11/2026).
 *
 * - por enquanto o pré-cadastro é só para turista;
 * - a aprovação pela portaria cria a pessoa, que fica no sistema para as
 *   próximas visitas; a selfie enviada vira a foto do cadastro;
 * - CPF já cadastrado: usa a pessoa existente, sem sobrescrever nada;
 * - aprovar NÃO cria autorização nem envia ao terminal facial: as 24 h
 *   começam no "Liberar entrada" da Validação, depois do pagamento.
 */
class PessoaDoPreCadastro
{
    public function __construct(
        private PrivateFileService $arquivos,
        private ValidadorFotoPessoa $validador,
        private AuditService $audit,
    ) {}

    /** Motivo que impede aprovar este pré-cadastro, ou null se pode. */
    public static function impedimento(PreRegistration $pre): ?string
    {
        if ($pre->access_type !== 'turista') {
            return 'Por enquanto só o pré-cadastro de turista pode ser aprovado. Rejeite ou peça correção.';
        }
        if (strlen(NormalizadorBusca::digitos((string) $pre->document)) !== 11) {
            return 'O CPF do pré-cadastro precisa ter 11 dígitos para criar a pessoa. Corrija o CPF antes de aprovar.';
        }

        return null;
    }

    /** Pessoa já cadastrada com o CPF do pré-cadastro, se houver. */
    public static function pessoaExistente(PreRegistration $pre): ?Pessoa
    {
        $cpf = NormalizadorBusca::digitos((string) $pre->document);
        if (strlen($cpf) !== 11) {
            return null;
        }

        $pessoaId = PessoaDocumento::query()->where('tipo', 'cpf')->where('valor_normalizado', $cpf)->whereNull('ended_at')->value('pessoa_id');

        return $pessoaId !== null ? Pessoa::query()->find($pessoaId) : null;
    }

    /**
     * Cria (ou reaproveita) a pessoa. Chamar dentro da transação da aprovação.
     *
     * @return array{pessoa: Pessoa, criada: bool}
     */
    public function registrar(PreRegistration $pre): array
    {
        if (($motivo = self::impedimento($pre)) !== null) {
            throw new InvalidArgumentException($motivo);
        }

        $cpf = NormalizadorBusca::digitos((string) $pre->document);

        return DB::transaction(function () use ($pre, $cpf): array {
            $existenteId = PessoaDocumento::query()->where('tipo', 'cpf')->where('valor_normalizado', $cpf)->whereNull('ended_at')->lockForUpdate()->value('pessoa_id');
            if ($existenteId !== null) {
                return ['pessoa' => Pessoa::query()->findOrFail($existenteId), 'criada' => false];
            }

            $pessoa = Pessoa::query()->create([
                'nome' => trim((string) $pre->name),
                'data_nascimento' => $pre->birth_date,
                'status' => 'ativo',
                'versao' => 1,
            ]);

            PessoaDocumento::query()->create([
                'pessoa_id' => $pessoa->id,
                'tipo' => 'cpf',
                'pais' => 'BR',
                'valor_normalizado' => $cpf,
                'valor_apresentacao' => substr($cpf, 0, 3).'.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-'.substr($cpf, 9, 2),
                'status' => 'ativo',
                'started_at' => now(),
            ]);

            foreach (['telefone' => trim((string) $pre->phone), 'email' => trim((string) $pre->email)] as $tipo => $valor) {
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

            $this->audit->record(
                action: 'pessoa_criada_pelo_pre_cadastro',
                module: 'pessoas',
                entityType: 'pessoas',
                entityId: $pessoa->id,
                metadata: ['pre_cadastro_id' => $pre->id, 'protocolo' => $pre->protocol],
            );

            return ['pessoa' => $pessoa, 'criada' => true];
        });
    }

    /**
     * A selfie aprovada vira a foto do cadastro, só se a pessoa ainda não tem
     * foto. Falha aqui não desfaz a aprovação: a foto é tirada no atendimento.
     */
    public function usarSelfieComoFoto(Pessoa $pessoa, PreRegistration $pre): bool
    {
        if ($pessoa->fotoVigente() !== null) {
            return false;
        }

        $selfie = $pre->currentFileLink('selfie')?->file;
        if ($selfie === null || ! $selfie->isAvailable()) {
            return false;
        }

        try {
            $conteudo = $this->arquivos->conteudo($selfie);
            if ($conteudo === null || $this->validador->problema($conteudo) !== null) {
                return false;
            }
            $this->arquivos->copiarParaFotoPessoa($pessoa, $conteudo, 'pre_cadastro_aprovado');
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    /** Turista que volta: já teve visita de turista ou pré-cadastro de turista aprovado. */
    public static function jaFoiTurista(Pessoa $pessoa): bool
    {
        if (AutorizacaoAcesso::query()->where('pessoa_id', $pessoa->id)->where('tipo', AutorizacaoAcesso::TIPO_TURISTA)->whereNull('imovel_id')->exists()) {
            return true;
        }

        // O documento do pré-cadastro pode vir com ou sem pontuação.
        $documentos = PessoaDocumento::query()->where('pessoa_id', $pessoa->id)->where('tipo', 'cpf')
            ->get(['valor_normalizado', 'valor_apresentacao'])
            ->flatMap(fn (PessoaDocumento $d) => [$d->valor_normalizado, $d->valor_apresentacao])->filter()->unique()->values()->all();

        return $documentos !== [] && PreRegistration::query()->where('status', 'aprovado')->where('access_type', 'turista')
            ->whereIn('document', $documentos)->exists();
    }
}
