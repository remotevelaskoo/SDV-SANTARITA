<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalização usada na busca da Validação de Entrada (AJ-006, fatia 1).
 *
 * As colunas de busca (`pessoas.nome_busca`, `pessoa_contatos.valor_busca`)
 * guardam o valor já normalizado, para que a consulta seja a mesma no
 * PostgreSQL e no SQLite e possa usar índice.
 */
final class NormalizadorBusca
{
    /** Minúsculas, sem acento e com espaços simples: "Ána  Lúcia" → "ana lucia". */
    public static function texto(?string $valor): string
    {
        $valor = Str::ascii(mb_strtolower((string) $valor));
        $valor = preg_replace('/[^a-z0-9]+/', ' ', $valor) ?? '';

        return trim($valor);
    }

    public static function digitos(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor) ?? '';
    }

    /** Documento com letras (RG, CNH antiga): só letras e números, em maiúsculas. */
    public static function documento(?string $valor): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $valor) ?? '');
    }

    /** Lista e cartões nunca mostram o documento inteiro (AJ-002). */
    public static function mascararDocumento(?string $documento): string
    {
        $digitos = self::digitos($documento);

        if (strlen($digitos) === 11) {
            return '***.***.'.substr($digitos, 6, 3).'-**';
        }

        $documento = trim((string) $documento);

        return mb_strlen($documento) > 4
            ? str_repeat('*', max(4, mb_strlen($documento) - 4)).mb_substr($documento, -4)
            : ($documento !== '' ? 'Documento protegido' : 'Não informado');
    }
}
