<?php

namespace Database\Seeders;

use App\Models\Implantacao;
use App\Models\Organizacao;
use Illuminate\Database\Seeder;

class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        $organizacao = Organizacao::query()->firstOrCreate(
            ['nome' => 'Soluções do Vale Tecnologia'],
            ['status' => 'ativa']
        );

        Implantacao::query()->firstOrCreate(
            ['slug' => 'santa-rita'],
            [
                'organizacao_id' => $organizacao->id,
                'nome' => 'Santa Rita',
                'status' => 'ativa',
            ]
        );
    }
}
