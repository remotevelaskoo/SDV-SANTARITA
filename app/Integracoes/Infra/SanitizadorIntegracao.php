<?php

namespace App\Integracoes\Infra;

/**
 * Redação central de dados técnicos antes de logs, ocorrências, auditoria
 * e resultados persistidos (ADR-007 §12, ADR-009 §11, ADR-010).
 *
 * Remove chaves sensíveis, cabeçalhos de autenticação, credenciais em URL,
 * blobs base64 (imagens, templates) e qualquer valor de segredo conhecido.
 */
class SanitizadorIntegracao
{
    public const REDIGIDO = '[REDIGIDO]';

    private const CHAVES_SENSIVEIS = '/pass(word)?|senha|secret|segredo|token|authori[sz]ation|cookie|credential|credencial_tecnica|api[_-]?key|face|facial|foto|photo|imagem|image|picture|selfie|template|biometr|documento|cpf/i';

    private const TAMANHO_MAXIMO_TEXTO = 500;

    /** @param  list<string>  $segredosConhecidos */
    public function texto(?string $texto, array $segredosConhecidos = []): ?string
    {
        if ($texto === null) {
            return null;
        }

        foreach ($segredosConhecidos as $segredo) {
            if ($segredo !== '') {
                $texto = str_replace($segredo, self::REDIGIDO, $texto);
            }
        }

        $texto = preg_replace('/(authorization|www-authenticate)\s*:\s*[^\r\n]+/i', '$1: '.self::REDIGIDO, $texto);
        $texto = preg_replace('#(\w+://)[^/@\s:]+:[^/@\s]+@#', '$1'.self::REDIGIDO.'@', $texto);
        $texto = preg_replace('/((?:password|senha|secret|token)\s*[=:]\s*)[^\s&,;"]+/i', '$1'.self::REDIGIDO, $texto);
        $texto = preg_replace('/data:[\w\/+.-]+;base64,[A-Za-z0-9+\/=]+/', '[BINARIO REMOVIDO]', $texto);
        $texto = preg_replace('/[A-Za-z0-9+\/]{120,}={0,2}/', '[BINARIO REMOVIDO]', $texto);

        return mb_strlen($texto) > self::TAMANHO_MAXIMO_TEXTO
            ? mb_substr($texto, 0, self::TAMANHO_MAXIMO_TEXTO).'…'
            : $texto;
    }

    /**
     * @param  array<array-key, mixed>  $dados
     * @param  list<string>  $segredosConhecidos
     * @return array<array-key, mixed>
     */
    public function dados(array $dados, array $segredosConhecidos = []): array
    {
        $limpo = [];

        foreach ($dados as $chave => $valor) {
            if (is_string($chave) && preg_match(self::CHAVES_SENSIVEIS, $chave)) {
                $limpo[$chave] = self::REDIGIDO;

                continue;
            }

            $limpo[$chave] = match (true) {
                is_array($valor) => $this->dados($valor, $segredosConhecidos),
                is_string($valor) => $this->texto($valor, $segredosConhecidos),
                is_scalar($valor), $valor === null => $valor,
                default => '['.get_debug_type($valor).']',
            };
        }

        return $limpo;
    }
}
