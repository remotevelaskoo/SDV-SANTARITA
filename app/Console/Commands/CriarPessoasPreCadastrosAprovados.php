<?php

namespace App\Console\Commands;

use App\Models\Implantacao;
use App\Models\PreRegistration;
use App\Services\PreCadastro\PessoaDoPreCadastro;
use App\Support\ImplantacaoContext;
use Illuminate\Console\Command;

/**
 * Pré-cadastros de turista aprovados antes de 13/11/2026 não criavam a
 * pessoa e por isso não apareciam na busca da Validação. Este comando cria
 * (ou reaproveita, pelo CPF) a pessoa de cada um. Pode rodar mais de uma vez.
 */
class CriarPessoasPreCadastrosAprovados extends Command
{
    protected $signature = 'pre-cadastro:criar-pessoas-aprovados';

    protected $description = 'Cria a pessoa dos pré-cadastros de turista já aprovados que ainda não têm cadastro';

    public function handle(PessoaDoPreCadastro $servico): int
    {
        $criadas = 0;
        $existentes = 0;
        $ignorados = 0;

        Implantacao::query()->where('status', 'ativa')->each(function (Implantacao $implantacao) use ($servico, &$criadas, &$existentes, &$ignorados): void {
            ImplantacaoContext::executarNo($implantacao, function () use ($servico, &$criadas, &$existentes, &$ignorados): void {
                PreRegistration::query()->where('status', 'aprovado')->each(function (PreRegistration $pre) use ($servico, &$criadas, &$existentes, &$ignorados): void {
                    if (PessoaDoPreCadastro::impedimento($pre) !== null) {
                        $ignorados++;
                        $this->line("Ignorado {$pre->protocol}: ".PessoaDoPreCadastro::impedimento($pre));

                        return;
                    }

                    $resultado = $servico->registrar($pre);
                    $servico->usarSelfieComoFoto($resultado['pessoa'], $pre);
                    $resultado['criada'] ? $criadas++ : $existentes++;
                    $this->line(($resultado['criada'] ? 'Criada' : 'Já existia').": {$resultado['pessoa']->nome} ({$pre->protocol})");
                });
            });
        });

        $this->info("Pessoas criadas: {$criadas}. Já cadastradas: {$existentes}. Ignorados (não turista ou sem CPF): {$ignorados}.");

        return self::SUCCESS;
    }
}
