<?php

namespace Database\Seeders;

use App\Models\HistoricoAcesso;
use App\Models\Imovel;
use App\Models\Implantacao;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * O demo original da P09/P06 tinha 8 registros, envolvendo pessoas que nunca
 * foram semeadas como Pessoa real (Sérgio Aparecido Luz, Camila Andrade,
 * Luciana Ferraz) ou que não têm Vínculo real (Eduardo Nogueira — ver
 * VinculoDemoSeeder). Apenas 3 registros têm Pessoa + Vínculo + Imóvel reais
 * disponíveis (VinculoDemoSeeder): Bianca Moretti, Rafael Domingues e
 * Mariana Souza. O operador "Marcos Almeida" do demo original também não
 * corresponde a nenhum usuário real semeado (PortariaDemoSeeder), então os
 * registros dele ficam com operator_id nulo — mesmo padrão já usado no
 * CaixaDemoSeeder.
 */
class HistoricoAcessoDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(VinculoDemoSeeder::class);
        $this->call(PortariaDemoSeeder::class);

        if (HistoricoAcesso::query()->exists()) {
            return;
        }

        $implantacaoId = Implantacao::query()->where('slug', 'santa-rita')->value('id');
        $tatianeId = User::query()->where('username', 'portaria')->value('id');

        $registros = [
            [
                'nome' => 'Bianca Moretti',
                'imovel' => 'SRA-A-208',
                'resultado' => 'negado',
                'motivo_negacao' => 'Responsável não localizado para confirmar a visita.',
                'notes' => 'Visitante orientada a retornar após contato com o morador.',
                'protocol' => 'SRA-20260810-004179',
                'occurred_at' => '2026-08-10 15:41:00',
                'operator_id' => $tatianeId,
            ],
            [
                'nome' => 'Rafael Domingues',
                'imovel' => 'SRA-C-501',
                'resultado' => 'liberado',
                'motivo_negacao' => null,
                'notes' => null,
                'protocol' => 'SRA-20260809-003988',
                'occurred_at' => '2026-08-09 18:12:00',
                'operator_id' => null,
            ],
            [
                'nome' => 'Mariana Souza',
                'imovel' => 'SRA-B-304',
                'resultado' => 'negado',
                'motivo_negacao' => 'Ponto de acesso não autorizado para o tipo de vínculo.',
                'notes' => null,
                'protocol' => 'SRA-20260809-003921',
                'occurred_at' => '2026-08-09 08:05:00',
                'operator_id' => null,
            ],
        ];

        foreach ($registros as $registro) {
            $pessoa = Pessoa::query()->where('nome', $registro['nome'])->first();
            $imovel = Imovel::query()->where('codigo', $registro['imovel'])->first();

            if ($pessoa === null || $imovel === null) {
                continue;
            }

            HistoricoAcesso::query()->create([
                'implantacao_id' => $implantacaoId,
                'pessoa_id' => $pessoa->id,
                'imovel_id' => $imovel->id,
                'ponto_acesso' => 'Portaria Principal',
                'tipo' => 'entrada',
                'resultado' => $registro['resultado'],
                'motivo_negacao' => $registro['motivo_negacao'],
                'operator_id' => $registro['operator_id'],
                'protocol' => $registro['protocol'],
                'notes' => $registro['notes'],
                'occurred_at' => $registro['occurred_at'],
            ]);
        }
    }
}
