<?php

namespace Tests\Feature;

use App\Models\Configuracao;
use App\Services\ContribuicoesAtendimento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfiguracaoValorContribuicaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_migracao_cria_a_configuracao_de_25_reais_quando_falta(): void
    {
        Configuracao::query()->where('chave', 'contribuicao.valor_padrao')->delete();

        $migracao = require database_path('migrations/2026_11_11_170000_garantir_configuracao_valor_contribuicao.php');
        $migracao->up();
        $migracao->up(); // idempotente

        $config = Configuracao::query()->where('chave', 'contribuicao.valor_padrao')->sole();
        $this->assertSame('25.00', $config->valor_padrao);
        $this->assertSame('contribuição e caixa', $config->categoria);
        $this->assertSame('25.00', ContribuicoesAtendimento::valorPadrao());
    }
}
