<?php

namespace App\Integracoes\Infra;

use App\Integracoes\Dominio\Dados\SegredoTecnico;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;

/**
 * Resolve a referência de um segredo técnico no momento do uso (ADR-009 §6
 * e §9). O banco guarda apenas a referência; o valor vem do ambiente de
 * execução. `vault:` ficará disponível quando o cofre de produção for
 * escolhido (ADR-009 §3, fornecedor definido na infraestrutura).
 */
class ResolvedorSegredo
{
    private const FORMATO = '/^(?<prefixo>[a-z]+):(?<nome>[A-Z][A-Z0-9_]{2,120})$/';

    public function validarReferencia(string $referencia): void
    {
        if (! preg_match(self::FORMATO, $referencia, $partes)) {
            throw new RegraIntegracaoViolada(
                'referencia_segredo_invalida',
                'Informe somente a referência do segredo, no formato env:NOME_DA_VARIAVEL. O valor da senha nunca é cadastrado no SDV.'
            );
        }

        if (! in_array($partes['prefixo'], (array) config('integracoes.segredos.prefixos'), true)) {
            throw new RegraIntegracaoViolada(
                'referencia_segredo_nao_suportada',
                "O mecanismo de segredo '{$partes['prefixo']}' ainda não está habilitado nesta instalação."
            );
        }
    }

    public function resolver(string $referencia): ?SegredoTecnico
    {
        $this->validarReferencia($referencia);
        [, $nome] = explode(':', $referencia, 2);

        $valor = getenv($nome);
        if ($valor === false) {
            $valor = $_ENV[$nome] ?? $_SERVER[$nome] ?? null;
        }

        return is_string($valor) && $valor !== '' ? new SegredoTecnico($valor) : null;
    }
}
