<?php

namespace Database\Seeders;

use App\Models\AutorizacaoAcesso;
use App\Models\CaixaTurno;
use App\Models\Contribuicao;
use App\Models\HistoricoAcesso;
use App\Models\Imovel;
use App\Models\Implantacao;
use App\Models\Pessoa;
use App\Models\User;
use App\Models\Veiculo;
use App\Models\Vinculo;
use App\Services\ContribuicoesAtendimento;
use App\Support\ImplantacaoContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Simulação para analisar o relatório final do Modo Portaria no ambiente
 * local (não roda no DatabaseSeeder): no caixa aberto do usuário "portaria"
 * (ou num turno novo), registra turistas da praia com placa, visitantes de
 * imóvel, um morador com veículo cadastrado, contribuições com ticket e
 * algumas saídas. Todos os dados são fictícios e marcados com protocolo
 * SIM-RF-; rodar de novo refaz a simulação.
 *
 *   php artisan db:seed --class=RelatorioFinalDemoSeeder
 */
class RelatorioFinalDemoSeeder extends Seeder
{
    /** @var list<array{nome: string, perfil: string, placa: ?string, contribui: string, forma: ?string, sai: bool}> */
    private const PESSOAS = [
        ['nome' => 'Rafaela Costa Lima', 'perfil' => 'turista', 'placa' => 'RTA1B23', 'contribui' => 'contribui', 'forma' => 'pix', 'sai' => true],
        ['nome' => 'Gustavo Henrique Prado', 'perfil' => 'turista', 'placa' => 'QWE4F56', 'contribui' => 'contribui', 'forma' => 'dinheiro', 'sai' => false],
        ['nome' => 'Larissa Mendes Rocha', 'perfil' => 'turista', 'placa' => 'PRA2C34', 'contribui' => 'contribui', 'forma' => 'pix', 'sai' => true],
        ['nome' => 'Diego Alves Martins', 'perfil' => 'turista', 'placa' => null, 'contribui' => 'contribui', 'forma' => 'dinheiro', 'sai' => false],
        ['nome' => 'Juliana Ferreira Dias', 'perfil' => 'turista', 'placa' => 'MAR7D89', 'contribui' => 'contribui', 'forma' => 'cartao', 'sai' => false],
        ['nome' => 'Thiago Barbosa Nunes', 'perfil' => 'turista', 'placa' => 'SOL3E21', 'contribui' => 'nao_contribui', 'forma' => null, 'sai' => true],
        ['nome' => 'Patrícia Gomes Silveira', 'perfil' => 'turista', 'placa' => null, 'contribui' => 'isento', 'forma' => null, 'sai' => false],
        ['nome' => 'Bruno Cardoso Teixeira', 'perfil' => 'turista', 'placa' => 'OND5G67', 'contribui' => 'contribui', 'forma' => 'pix', 'sai' => false],
        // Só turista contribui (regra do PO de 11/11/2026): visitante e morador ficam sem contribuição.
        ['nome' => 'Fernanda Ribeiro Castro', 'perfil' => 'visitante', 'placa' => 'VIS9H12', 'contribui' => 'nao_se_aplica', 'forma' => null, 'sai' => true],
        ['nome' => 'Ricardo Moura Santos', 'perfil' => 'visitante', 'placa' => null, 'contribui' => 'nao_se_aplica', 'forma' => null, 'sai' => false],
        ['nome' => 'Helena Duarte Vieira', 'perfil' => 'morador', 'placa' => 'CAS4J56', 'contribui' => 'nao_se_aplica', 'forma' => null, 'sai' => true],
    ];

    public function run(): void
    {
        $implantacao = Implantacao::query()->where('slug', 'santa-rita')->first();
        $operador = User::query()->where('username', 'portaria')->first();
        if (! $implantacao || ! $operador) {
            $this->command?->warn('Implantação santa-rita ou usuário "portaria" não encontrados.');

            return;
        }

        ImplantacaoContext::executarNo($implantacao, fn () => $this->simular($operador));
    }

    private function simular(User $operador): void
    {
        $this->limparSimulacaoAnterior();

        $turno = CaixaTurno::query()->where('operator_id', $operador->id)->where('status', 'aberto')->latest('opened_at')->first()
            ?? CaixaTurno::query()->create([
                'terminal' => 'Caixa 01 — Guarita',
                'operator_id' => $operador->id,
                'opened_at' => now()->subHours(3),
                'opening_balance' => 200,
                'status' => 'aberto',
            ]);

        $imovel = Imovel::query()->orderBy('codigo')->first();
        // O CaixaDemoSeeder abre o caixa às 13:00 do dia em que rodou; se isso
        // ainda não chegou, a abertura simulada passa para 3 h atrás, para os
        // atendimentos caberem no turno.
        if ($turno->opened_at->gt(now()->subMinutes(30))) {
            $turno->forceFill(['opened_at' => now()->subHours(3)])->save();
        }

        // Distribui os atendimentos entre a abertura do caixa (no máximo 3 h atrás) e agora.
        $inicio = $turno->opened_at->max(now()->subHours(3));
        $passo = max(1, (int) floor($inicio->diffInMinutes(now()->subMinutes(2)) / (count(self::PESSOAS) + 2)));
        $momento = $inicio->copy();
        $ticket = 3100;
        $saidas = [];

        foreach (self::PESSOAS as $i => $dados) {
            $momento = $momento->copy()->addMinutes($passo);
            $pessoa = Pessoa::query()->create(['nome' => $dados['nome'], 'status' => 'ativo', 'versao' => 1]);
            $veiculo = null;

            if ($dados['perfil'] === 'morador' && $imovel) {
                Vinculo::query()->create([
                    'pessoa_id' => $pessoa->id, 'imovel_id' => $imovel->id, 'tipo' => 'morador', 'papel' => 'titular',
                    'status' => 'ativo', 'origem' => 'cadastro_manual', 'started_at' => now()->subYear(), 'versao' => 1,
                ]);
                $veiculo = Veiculo::query()->firstOrCreate(
                    ['plate_normalized' => Veiculo::normalizePlate($dados['placa'])],
                    ['plate_display' => $dados['placa'], 'country' => 'BR', 'type' => 'carro', 'brand' => 'Fiat', 'model' => 'Argo', 'color' => 'Prata', 'status' => 'ativo'],
                );
            } else {
                AutorizacaoAcesso::query()->create([
                    'pessoa_id' => $pessoa->id,
                    'imovel_id' => $dados['perfil'] === 'visitante' ? $imovel?->id : null,
                    'tipo' => AutorizacaoAcesso::TIPO_TURISTA,
                    'inicio' => $momento->copy()->subMinute(),
                    'fim' => $momento->copy()->addHours(24),
                    'status' => 'ativa',
                    'responsavel' => $dados['perfil'] === 'visitante' ? 'Morador responsável (simulação)' : null,
                    'created_by' => $operador->id,
                ]);
            }

            $entrada = $this->acesso($pessoa, $operador, 'entrada', $momento, $i, $veiculo, $dados['perfil'] === 'turista' ? null : $imovel, $veiculo ? null : $dados['placa']);

            $pago = $dados['contribui'] === 'contribui';
            if ($dados['contribui'] !== 'nao_se_aplica') {
                Contribuicao::query()->create([
                    'historico_acesso_id' => $entrada->id,
                    'pessoa_id' => $pessoa->id,
                    'classificacao' => $dados['contribui'],
                    'situacao_pagamento' => match ($dados['contribui']) {
                        'contribui' => 'pago',
                        'isento' => 'isento',
                        default => 'sem_pagamento',
                    },
                    'valor' => $pago ? ContribuicoesAtendimento::valorPadrao() : null,
                    'forma_pagamento' => $pago ? $dados['forma'] : null,
                    'ticket_numero' => $pago ? sprintf('%06d', $ticket++) : null,
                    'caixa_turno_id' => $turno->id,
                    'operator_id' => $operador->id,
                    'registrada_em' => $momento,
                    'versao' => 1,
                ]);
            }

            if ($dados['sai']) {
                $saidas[] = [$pessoa, $veiculo, $dados['perfil'] === 'turista' ? null : $imovel, $veiculo ? null : $dados['placa']];
            }
        }

        foreach ($saidas as $j => [$pessoa, $veiculo, $imovelSaida, $placa]) {
            $momento = $momento->copy()->addMinutes(max(1, intdiv($passo, 2)));
            $this->acesso($pessoa, $operador, 'saida', $momento->min(now()), 100 + $j, $veiculo, $imovelSaida, $placa);
        }

        $this->command?->info('Simulação criada: '.count(self::PESSOAS).' entradas e '.count($saidas).' saídas no turno '.$turno->terminal.'.');
    }

    /**
     * Rodar de novo refaz a simulação: apaga só o que ela mesma criou
     * (atendimentos SIM-RF-, suas contribuições, visitas, vínculos e pessoas).
     */
    private function limparSimulacaoAnterior(): void
    {
        $historicos = DB::table('historico_acessos')->where('protocol', 'like', 'SIM-RF-%');
        $pessoaIds = (clone $historicos)->pluck('pessoa_id')->filter()->unique()->values();
        if ($pessoaIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($historicos, $pessoaIds): void {
            $historicoIds = (clone $historicos)->pluck('id');
            $contribuicaoIds = DB::table('contribuicoes')->whereIn('historico_acesso_id', $historicoIds)->pluck('id');
            DB::table('contribuicao_ticket_correcoes')->whereIn('contribuicao_id', $contribuicaoIds)->delete();
            DB::table('contribuicoes')->whereIn('id', $contribuicaoIds)->delete();
            DB::table('historico_acessos')->whereIn('id', $historicoIds)->delete();
            DB::table('autorizacoes_acesso')->whereIn('pessoa_id', $pessoaIds)->delete();
            DB::table('vinculos')->whereIn('pessoa_id', $pessoaIds)->delete();
            DB::table('pessoas')->whereIn('id', $pessoaIds)->delete();
        });

        $this->command?->info('Simulação anterior do relatório final removida.');
    }

    private function acesso(Pessoa $pessoa, User $operador, string $tipo, Carbon $quando, int $n, ?Veiculo $veiculo, ?Imovel $imovel, ?string $placa): HistoricoAcesso
    {
        return HistoricoAcesso::query()->create([
            'pessoa_id' => $pessoa->id,
            'imovel_id' => $imovel?->id,
            'veiculo_id' => $veiculo?->id,
            'placa_informada' => $placa,
            'placa_origem' => $placa ? 'manual' : null,
            'ponto_acesso' => 'Portaria Principal',
            'tipo' => $tipo,
            'resultado' => 'liberado',
            'operator_id' => $operador->id,
            'protocol' => 'SIM-RF-'.$quando->format('Ymd').'-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
            'occurred_at' => $quando,
        ]);
    }
}
