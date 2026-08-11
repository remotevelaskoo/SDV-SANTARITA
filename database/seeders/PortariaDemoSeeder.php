<?php

namespace Database\Seeders;

use App\Models\Implantacao;
use App\Models\PreRegistration;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PortariaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(FoundationSeeder::class);

        $implantacaoId = Implantacao::query()->where('slug', 'santa-rita')->value('id');

        User::query()->updateOrCreate(
            ['username' => 'portaria'],
            [
                'name' => 'Tatiane Souza',
                'email' => 'tatiane.souza@sdv-santarita.local',
                'password' => Hash::make('sdv2026'),
            ]
        );

        User::query()->updateOrCreate(
            ['username' => 'portaria.leitura'],
            [
                'name' => 'Operador de Leitura',
                'email' => 'operador.leitura@sdv-santarita.local',
                'password' => Hash::make('sdv2026'),
            ]
        );

        User::query()->updateOrCreate(
            ['username' => 'gestor'],
            [
                'name' => 'Marcos Vieira',
                'email' => 'marcos.vieira@sdv-santarita.local',
                'password' => Hash::make('sdv2026'),
            ]
        );

        User::query()->updateOrCreate(
            ['username' => 'administrador'],
            [
                'name' => 'Beatriz Cardoso',
                'email' => 'beatriz.cardoso@sdv-santarita.local',
                'password' => Hash::make('sdv2026'),
            ]
        );

        if (PreRegistration::query()->exists()) {
            return;
        }

        $now = now();

        PreRegistration::query()->create([
            'implantacao_id' => $implantacaoId,
            'protocol' => 'PRE-SRA-X7K9M2',
            'name' => 'Camila Andrade',
            'document' => '***.***.331-**',
            'birth_date' => '1993-03-14',
            'phone' => '(12) 99876-4321',
            'email' => 'camila.andrade@example.com',
            'access_type' => 'visitante',
            'address_informed' => 'Rua das Palmeiras, 125 · Centro · Taubaté/SP',
            'destination_property' => 'Bloco B — Apto 304',
            'destination_label' => 'Bloco B — Apto 304',
            'responsible_name' => 'Mariana Souza',
            'period_start' => $now->copy()->addDay()->setTime(18, 0),
            'period_end' => $now->copy()->addDay()->setTime(22, 0),
            'vehicle_plate' => 'ABC1D23',
            'document_status' => 'Documento enviado e legível',
            'selfie_status' => 'Selfie enviada e adequada',
            'status' => 'aguardando',
            'alert' => 'Aguardando há mais de 24 horas',
            'submitted_at' => $now->copy()->subDay()->setTime(15, 57),
        ]);

        PreRegistration::query()->create([
            'implantacao_id' => $implantacaoId,
            'protocol' => 'PRE-SRA-M4N8Q1',
            'name' => 'Paulo Henrique Lima',
            'document' => '***.***.760-**',
            'birth_date' => '1987-08-22',
            'phone' => '(12) 99720-1144',
            'email' => 'paulo.lima@example.com',
            'access_type' => 'prestador',
            'address_informed' => 'Av. Independência, 840 · Taubaté/SP',
            'destination_property' => null,
            'destination_label' => 'Área comum · Manutenção',
            'responsible_name' => 'Síndica Ana Ferreira',
            'period_start' => $now->copy()->addDays(2)->setTime(8, 0),
            'period_end' => $now->copy()->addDays(2)->setTime(17, 0),
            'vehicle_plate' => 'DEF4G56',
            'document_status' => 'Documento enviado e legível',
            'selfie_status' => 'Selfie enviada e adequada',
            'status' => 'aguardando',
            'alert' => null,
            'submitted_at' => $now->copy()->subHours(6),
        ]);

        PreRegistration::query()->create([
            'implantacao_id' => $implantacaoId,
            'protocol' => 'PRE-SRA-C2P5T8',
            'name' => 'Renata Alves',
            'document' => '***.***.218-**',
            'birth_date' => '1990-12-05',
            'phone' => '(11) 99654-7788',
            'email' => 'renata.alves@example.com',
            'access_type' => 'turista',
            'address_informed' => 'Rua Bela Cintra, 312 · São Paulo/SP',
            'destination_property' => null,
            'destination_label' => 'Praia do Santa Rita',
            'responsible_name' => null,
            'period_start' => $now->copy()->addDays(3)->setTime(0, 0),
            'period_end' => $now->copy()->addDays(7)->setTime(0, 0),
            'vehicle_plate' => null,
            'document_status' => 'Documento enviado e legível',
            'selfie_status' => 'Reenvio solicitado',
            'status' => 'correcao',
            'alert' => 'Selfie precisa ser reenviada',
            'submitted_at' => $now->copy()->subHours(4),
            'status_changed_at' => $now->copy()->subHours(3),
        ]);

        PreRegistration::query()->create([
            'implantacao_id' => $implantacaoId,
            'protocol' => 'PRE-SRA-R6V3B7',
            'name' => 'Felipe Martins',
            'document' => '***.***.004-**',
            'birth_date' => '1985-06-19',
            'phone' => '(12) 99118-2020',
            'email' => 'felipe.martins@example.com',
            'access_type' => 'visitante',
            'address_informed' => 'Rua das Acácias, 42 · Taubaté/SP',
            'destination_property' => 'Bloco A — Apto 112',
            'destination_label' => 'Bloco A — Apto 112',
            'responsible_name' => 'Eduardo Nogueira',
            'period_start' => $now->copy()->subDay()->setTime(14, 0),
            'period_end' => $now->copy()->subDay()->setTime(20, 0),
            'vehicle_plate' => 'GHI7J89',
            'document_status' => 'Documento conferido',
            'selfie_status' => 'Selfie conferida',
            'status' => 'aprovado',
            'alert' => null,
            'submitted_at' => $now->copy()->subDays(2)->setTime(19, 21),
            'status_changed_at' => $now->copy()->subDay(),
        ]);

        PreRegistration::query()->create([
            'implantacao_id' => $implantacaoId,
            'protocol' => 'PRE-SRA-H9D2K4',
            'name' => 'Sérgio Luz',
            'document' => '***.***.447-**',
            'birth_date' => '1979-01-30',
            'phone' => '(12) 98876-1004',
            'email' => 'sergio.luz@example.com',
            'access_type' => 'prestador',
            'address_informed' => 'Rua Projetada, 91 · Pindamonhangaba/SP',
            'destination_property' => null,
            'destination_label' => 'Bloco B · Apto 706',
            'responsible_name' => 'Luciana Ferraz',
            'period_start' => $now->copy()->subDays(3)->setTime(9, 0),
            'period_end' => $now->copy()->subDays(3)->setTime(12, 0),
            'vehicle_plate' => null,
            'document_status' => 'Documento incompleto',
            'selfie_status' => 'Selfie enviada',
            'status' => 'rejeitado',
            'alert' => null,
            'submitted_at' => $now->copy()->subDays(4)->setTime(16, 30),
            'status_changed_at' => $now->copy()->subDays(3),
        ]);
    }
}
