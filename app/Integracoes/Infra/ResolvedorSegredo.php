<?php

namespace App\Integracoes\Infra;

use App\Integracoes\Dominio\Dados\SegredoTecnico;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\EquipamentoCredencial;
use Illuminate\Contracts\Encryption\DecryptException;

/**
 * Resolve o segredo técnico no momento do uso (ADR-009 §6 e §9).
 *
 * Dois mecanismos:
 * - `env:NOME`: o banco guarda só a referência; o valor vem do ambiente;
 * - `cifrado:BANCO`: o valor foi digitado pelo administrador na tela e está
 *   cifrado com a chave da aplicação (APP_KEY). Aceito para desenvolvimento
 *   e homologação; produção continua exigindo o cofre (ADR-009 §3).
 *
 * `vault:` ficará disponível quando o cofre de produção for escolhido.
 */
class ResolvedorSegredo
{
    private const FORMATO = '/^(?<prefixo>[a-z]+):(?<nome>[A-Z][A-Z0-9_]{2,120})$/';

    public function validarReferencia(string $referencia): void
    {
        if (! preg_match(self::FORMATO, $referencia, $partes)) {
            throw new RegraIntegracaoViolada(
                'referencia_segredo_invalida',
                'Informe somente a referência do segredo, no formato env:NOME_DA_VARIAVEL.'
            );
        }

        if (! in_array($partes['prefixo'], (array) config('integracoes.segredos.prefixos'), true)) {
            throw new RegraIntegracaoViolada(
                'referencia_segredo_nao_suportada',
                "O mecanismo de segredo '{$partes['prefixo']}' ainda não está habilitado nesta instalação."
            );
        }
    }

    public function resolverCredencial(EquipamentoCredencial $credencial): ?SegredoTecnico
    {
        if (! $credencial->cifrada()) {
            return $this->resolver($credencial->referencia_segredo);
        }

        if (! (bool) config('integracoes.segredos.cifrado_permitido')) {
            return null;
        }

        try {
            $valor = $credencial->segredo_cifrado;
        } catch (DecryptException) {
            // Chave da aplicação trocada: falha segura, sem repetir o conteúdo.
            return null;
        }

        return is_string($valor) && $valor !== '' ? new SegredoTecnico($valor) : null;
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
