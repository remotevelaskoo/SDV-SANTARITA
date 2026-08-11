<?php

namespace Database\Seeders;

use App\Models\Encomenda;
use App\Models\Imovel;
use App\Models\Implantacao;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dados de demonstração das encomendas, com os mesmos protocolos e
 * destinatários já usados no protótipo de PackageManagement.php (P15). A
 * encomenda de Eduardo Nogueira (Bloco A — Apto 112) fica de fora porque
 * esse código não existe entre os imóveis semeados — mesma inconsistência
 * de dados de demonstração pré-existente já registrada no
 * VinculoDemoSeeder.
 */
class EncomendaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(FoundationSeeder::class);

        if (Encomenda::query()->exists()) {
            return;
        }

        $implantacaoId = Implantacao::query()->where('slug', 'santa-rita')->value('id');
        $operador = User::query()->where('username', 'portaria')->value('id');
        $now = now();

        $encomendas = [
            [
                'protocol' => 'SRE-20260810-0041',
                'recipient_name' => 'Marcos Vinicius da Silva',
                'imovel' => 'SRA-A-102',
                'carrier' => 'Correios',
                'type' => 'caixa',
                'storage_location' => 'Prateleira A3',
                'status' => 'entregue',
                'received_at' => $now->copy()->subDay()->setTime(9, 12),
                'notified_at' => $now->copy()->subDay()->setTime(9, 14),
                'delivered_at' => $now->copy()->subDay()->setTime(18, 30),
                'delivered_to' => 'Marcos Vinicius da Silva',
            ],
            [
                'protocol' => 'SRE-20260810-0042',
                'recipient_name' => 'Bianca Moretti',
                'imovel' => 'SRA-A-208',
                'carrier' => 'Mercado Livre',
                'type' => 'caixa',
                'storage_location' => 'Prateleira B1',
                'status' => 'avisado',
                'received_at' => $now->copy()->subDay()->setTime(11, 5),
                'notified_at' => $now->copy()->subDay()->setTime(11, 7),
                'notes' => 'Volume grande, não empilhar.',
            ],
            [
                'protocol' => 'SRE-20260810-0043',
                'recipient_name' => 'Rafael Domingues',
                'imovel' => 'SRA-C-501',
                'carrier' => 'Amazon',
                'type' => 'envelope',
                'storage_location' => 'Gaveta C2',
                'status' => 'aguardando',
                'received_at' => $now->copy()->subDay()->setTime(14, 40),
            ],
            [
                'protocol' => 'SRE-20260810-0044',
                'recipient_name' => 'Mariana Souza',
                'imovel' => 'SRA-B-304',
                'carrier' => 'Transportadora Vale Express',
                'type' => 'volume',
                'storage_location' => 'Depósito — Setor 2',
                'status' => 'aguardando',
                'received_at' => $now->copy()->subDay()->setTime(16, 20),
                'notes' => 'Requer duas pessoas para transporte.',
            ],
        ];

        foreach ($encomendas as $dados) {
            $imovel = Imovel::query()->where('codigo', $dados['imovel'])->first();

            if ($imovel === null) {
                continue;
            }

            Encomenda::query()->create([
                'implantacao_id' => $implantacaoId,
                'protocol' => $dados['protocol'],
                'recipient_name' => $dados['recipient_name'],
                'imovel_id' => $imovel->id,
                'carrier' => $dados['carrier'],
                'type' => $dados['type'],
                'storage_location' => $dados['storage_location'],
                'status' => $dados['status'],
                'received_at' => $dados['received_at'],
                'received_by' => $operador,
                'notified_at' => $dados['notified_at'] ?? null,
                'delivered_at' => $dados['delivered_at'] ?? null,
                'delivered_to' => $dados['delivered_to'] ?? null,
                'notes' => $dados['notes'] ?? null,
            ]);
        }
    }
}
