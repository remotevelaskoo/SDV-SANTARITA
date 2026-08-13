<?php

namespace Database\Seeders;

use App\Models\Configuracao;
use Illuminate\Database\Seeder;

/**
 * Catálogo global de configurações (P17 fatia 3) — não depende de
 * implantação, roda uma única vez. O valor por implantação (quando
 * customizado) vive em `implantacao_configuracoes`.
 */
class ConfiguracaoSeeder extends Seeder
{
    /** @var list<array<string, mixed>> */
    private const CATALOGO = [
        [
            'chave' => 'geral.telefone_contato',
            'categoria' => 'dados gerais',
            'tipo' => 'texto',
            'rotulo' => 'Telefone de contato da portaria',
            'descricao' => 'Usado em relatórios e comunicações administrativas.',
            'valor_padrao' => null,
        ],
        [
            'chave' => 'geral.email_contato',
            'categoria' => 'dados gerais',
            'tipo' => 'texto',
            'rotulo' => 'E-mail de contato administrativo',
            'descricao' => null,
            'valor_padrao' => null,
        ],
        [
            'chave' => 'caixa.saldo_sugerido_abertura',
            'categoria' => 'contribuição e caixa',
            'tipo' => 'numero',
            'rotulo' => 'Valor sugerido para abertura de caixa',
            'descricao' => 'Preenchido automaticamente no formulário de abertura de turno; o operador pode ajustar.',
            'valor_padrao' => '200.00',
        ],
    ];

    public function run(): void
    {
        foreach (self::CATALOGO as $dados) {
            Configuracao::query()->firstOrCreate(['chave' => $dados['chave']], $dados);
        }
    }
}
