<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(PortariaDemoSeeder::class);
        $this->call(ImovelDemoSeeder::class);
        $this->call(PessoaDemoSeeder::class);
        $this->call(VinculoDemoSeeder::class);
        $this->call(VeiculoDemoSeeder::class);
        $this->call(EmpresaDemoSeeder::class);
        $this->call(EncomendaDemoSeeder::class);
        $this->call(CaixaDemoSeeder::class);
        $this->call(HistoricoAcessoDemoSeeder::class);
        $this->call(UsuarioDemoSeeder::class);
    }
}
