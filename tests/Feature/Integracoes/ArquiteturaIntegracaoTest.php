<?php

namespace Tests\Feature\Integracoes;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Testes arquiteturais do ADR-007 (CA-ADR-007-001 e 003) e do ADR-016
 * (CA-ADR-016-007): o fabricante fica confinado ao seu adaptador e o
 * núcleo não depende de adaptadores concretos.
 */
class ArquiteturaIntegracaoTest extends TestCase
{
    /** @return iterable<string, string> caminho relativo => conteúdo */
    private function arquivosPhp(string $diretorio): iterable
    {
        foreach ((new Finder)->files()->in(base_path($diretorio))->name('*.php') as $arquivo) {
            yield $diretorio.'/'.$arquivo->getRelativePathname() => $arquivo->getContents();
        }
    }

    public function test_nome_do_fabricante_so_aparece_no_proprio_adaptador(): void
    {
        foreach ($this->arquivosPhp('app') as $caminho => $conteudo) {
            if (str_starts_with($caminho, 'app/Integracoes/Adaptadores/Hikvision/')) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression('/hikvision|isapi/i', $conteudo, "{$caminho} referencia o fabricante fora do adaptador.");
        }
    }

    public function test_nucleo_e_aplicacao_nao_dependem_de_adaptadores_concretos(): void
    {
        foreach (['app/Integracoes/Dominio', 'app/Integracoes/Aplicacao', 'app/Models', 'app/Livewire', 'app/Services', 'app/Jobs'] as $diretorio) {
            foreach ($this->arquivosPhp($diretorio) as $caminho => $conteudo) {
                $this->assertStringNotContainsString('App\\Integracoes\\Adaptadores', $conteudo, "{$caminho} depende de um adaptador concreto.");
            }
        }
    }

    public function test_contratos_de_dominio_nao_dependem_de_laravel_nem_de_models(): void
    {
        foreach ($this->arquivosPhp('app/Integracoes/Dominio') as $caminho => $conteudo) {
            $this->assertDoesNotMatchRegularExpression('/use (Illuminate|App\\\\Models|App\\\\Integracoes\\\\(Infra|Aplicacao))/', $conteudo, "{$caminho} acopla o contrato à infraestrutura.");
        }
    }

    public function test_adaptadores_nao_acessam_banco_nem_models(): void
    {
        foreach ($this->arquivosPhp('app/Integracoes/Adaptadores') as $caminho => $conteudo) {
            $this->assertDoesNotMatchRegularExpression('/use (App\\\\Models|Illuminate\\\\Support\\\\Facades\\\\DB|Illuminate\\\\Database)/', $conteudo, "{$caminho} acessa persistência.");
        }
    }
}
