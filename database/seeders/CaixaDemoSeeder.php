<?php

namespace Database\Seeders;

use App\Models\CaixaMovimentacao;
use App\Models\CaixaTurno;
use App\Models\Implantacao;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dados de demonstração do caixa, com o mesmo turno aberto e histórico já
 * usados no protótipo de CashRegister.php (P14). A sessão fechada de
 * "Marcos Almeida" fica sem operador vinculado — esse nome não corresponde
 * a nenhuma conta demo real semeada (só as contas de PortariaDemoSeeder/
 * UsuarioDemoSeeder existem), mesmo critério já usado para prestadores e
 * vínculos sem Pessoa real.
 */
class CaixaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(FoundationSeeder::class);

        if (CaixaTurno::query()->exists()) {
            return;
        }

        $implantacaoId = Implantacao::query()->where('slug', 'santa-rita')->value('id');
        $portaria = User::query()->where('username', 'portaria')->value('id');
        $now = now();

        $turnoAberto = CaixaTurno::query()->create([
            'implantacao_id' => $implantacaoId,
            'terminal' => 'Caixa 01 — Guarita',
            'operator_id' => $portaria,
            'opened_at' => $now->copy()->setTime(13, 0),
            'opening_balance' => 200.00,
            'status' => 'aberto',
        ]);

        $movimentacoes = [
            ['time' => [13, 5], 'type' => 'entrada', 'amount' => 15.00, 'method' => 'dinheiro', 'description' => 'Contribuição — visitante Camila Andrade', 'protocol' => 'SRA-20260810-004112'],
            ['time' => [13, 33], 'type' => 'entrada', 'amount' => 15.00, 'method' => 'pix', 'description' => 'Contribuição — visitante Camila Andrade', 'protocol' => 'SRA-20260810-004150'],
            ['time' => [15, 33], 'type' => 'entrada', 'amount' => 20.00, 'method' => 'dinheiro', 'description' => 'Contribuição — prestador Sérgio Aparecido Luz', 'protocol' => 'SRA-20260810-004178'],
            ['time' => [15, 41], 'type' => 'estorno', 'amount' => 15.00, 'method' => 'dinheiro', 'description' => 'Estorno — entrada negada (Bianca Moretti)', 'protocol' => 'SRA-20260810-004179'],
        ];

        foreach ($movimentacoes as $dados) {
            CaixaMovimentacao::query()->create([
                'implantacao_id' => $implantacaoId,
                'caixa_turno_id' => $turnoAberto->id,
                'type' => $dados['type'],
                'amount' => $dados['amount'],
                'method' => $dados['method'],
                'description' => $dados['description'],
                'protocol' => $dados['protocol'],
                'operator_id' => $portaria,
                'occurred_at' => $now->copy()->setTime(...$dados['time']),
            ]);
        }

        $sessoesFechadas = [
            [
                'operator_id' => null,
                'opened_at' => $now->copy()->subDays(2)->setTime(7, 0),
                'closed_at' => $now->copy()->subDays(2)->setTime(19, 0),
                'opening_balance' => 150.00,
                'expected_amount' => 612.50,
                'informed_amount' => 612.50,
                'difference' => 0.0,
                'closing_status' => 'conferido',
            ],
            [
                'operator_id' => $portaria,
                'opened_at' => $now->copy()->subDays(3)->setTime(7, 0),
                'closed_at' => $now->copy()->subDays(3)->setTime(19, 0),
                'opening_balance' => 150.00,
                'expected_amount' => 498.00,
                'informed_amount' => 490.00,
                'difference' => -8.0,
                'closing_status' => 'diferenca',
            ],
        ];

        foreach ($sessoesFechadas as $dados) {
            CaixaTurno::query()->create([
                'implantacao_id' => $implantacaoId,
                'terminal' => 'Caixa 01 — Guarita',
                'operator_id' => $dados['operator_id'],
                'opened_at' => $dados['opened_at'],
                'opening_balance' => $dados['opening_balance'],
                'closed_at' => $dados['closed_at'],
                'informed_amount' => $dados['informed_amount'],
                'expected_amount' => $dados['expected_amount'],
                'difference' => $dados['difference'],
                'status' => 'fechado',
                'closing_status' => $dados['closing_status'],
            ]);
        }
    }
}
