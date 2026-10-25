<?php

namespace App\Support;

use App\Models\PreRegistration;
use App\Models\PreRegistrationDocumentExtraction;
use Illuminate\Support\Carbon;

/**
 * Compara, campo a campo, o que a leitura automática extraiu, o que o
 * solicitante confirmou e o valor atual do pré-cadastro (AJ-006, fatia 3).
 *
 * A comparação é só apoio à conferência humana: nenhuma situação aqui
 * aprova, rejeita ou autoriza nada.
 */
class ComparacaoOcrPreCadastro
{
    /** Abaixo deste valor a confiança do campo é destacada para conferência. */
    public const CONFIANCA_BAIXA = 0.6;

    private const CAMPOS = [
        'name' => 'Nome',
        'cpf' => 'CPF',
        'birth_date' => 'Data de nascimento',
        'document_number' => 'Número do documento',
    ];

    /**
     * @return list<array{campo: string, rotulo: string, extraido: ?string, confianca: ?float, confirmado: ?string, atual: ?string, sensivel: bool, situacao: string, tom: string}>
     */
    public static function linhas(PreRegistration $registro, PreRegistrationDocumentExtraction $leitura): array
    {
        $extraidos = $leitura->extracted_fields ?? [];
        $confiancas = $leitura->field_confidences ?? [];
        $confirmados = $leitura->confirmed_fields ?? [];
        $atuais = [
            'name' => $registro->name,
            'cpf' => $registro->document,
            'birth_date' => $registro->birth_date?->format('Y-m-d'),
            'document_number' => null,
        ];

        $linhas = [];
        foreach (self::CAMPOS as $campo => $rotulo) {
            $extraido = self::texto($extraidos[$campo] ?? null);
            $confirmado = self::texto($confirmados[$campo] ?? null);
            $confianca = isset($confiancas[$campo]) && is_numeric($confiancas[$campo]) ? (float) $confiancas[$campo] : null;
            $atual = self::texto($atuais[$campo]);

            [$situacao, $tom] = self::situacao($campo, $extraido, $confianca, $confirmado, $atual);

            $linhas[] = [
                'campo' => $campo,
                'rotulo' => $rotulo,
                'extraido' => $campo === 'birth_date' ? self::data($extraido) : $extraido,
                'confianca' => $confianca,
                'confirmado' => $campo === 'birth_date' ? self::data($confirmado) : $confirmado,
                'atual' => $campo === 'birth_date' ? self::data($atual) : $atual,
                'sensivel' => in_array($campo, ['cpf', 'document_number'], true),
                'situacao' => $situacao,
                'tom' => $tom,
            ];
        }

        return $linhas;
    }

    /** @param list<array{tom: string}> $linhas */
    public static function divergencias(array $linhas): int
    {
        return count(array_filter($linhas, fn (array $linha) => $linha['tom'] !== 'success' && $linha['tom'] !== 'neutral'));
    }

    /** @return array{0: string, 1: string} */
    private static function situacao(string $campo, ?string $extraido, ?float $confianca, ?string $confirmado, ?string $atual): array
    {
        if ($extraido === null) {
            return ['Não lido automaticamente', 'neutral'];
        }

        if ($confirmado !== null && ! self::iguais($campo, $extraido, $confirmado)) {
            return ['Alterado pelo solicitante', 'warning'];
        }

        if ($atual !== null && $confirmado !== null && ! self::iguais($campo, $confirmado, $atual)) {
            return ['Corrigido na análise', 'info'];
        }

        if ($confianca !== null && $confianca < self::CONFIANCA_BAIXA) {
            return ['Confiança baixa', 'warning'];
        }

        return ['Confere', 'success'];
    }

    private static function iguais(string $campo, string $a, string $b): bool
    {
        return match ($campo) {
            'cpf' => NormalizadorBusca::digitos($a) === NormalizadorBusca::digitos($b),
            'document_number' => NormalizadorBusca::documento($a) === NormalizadorBusca::documento($b),
            'birth_date' => self::data($a) === self::data($b),
            default => NormalizadorBusca::texto($a) === NormalizadorBusca::texto($b),
        };
    }

    private static function texto(mixed $valor): ?string
    {
        if (! is_scalar($valor)) {
            return null;
        }

        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    /** Apresenta datas como dd/mm/aaaa, aceitando aaaa-mm-dd ou dd/mm/aaaa. */
    private static function data(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y'] as $formato) {
            try {
                $data = Carbon::createFromFormat('!'.$formato, $valor);
                if ($data !== null && $data->format($formato) === $valor) {
                    return $data->format('d/m/Y');
                }
            } catch (\Throwable) {
                // tenta o próximo formato
            }
        }

        return $valor;
    }
}
