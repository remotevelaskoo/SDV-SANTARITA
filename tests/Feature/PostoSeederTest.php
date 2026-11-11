<?php

namespace Tests\Feature;

use App\Models\Implantacao;
use App\Models\Posto;
use App\Support\ImplantacaoContext;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\PostoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        ImplantacaoContext::forgetCurrent();
        parent::tearDown();
    }

    public function test_initial_posts_are_seeded_without_model_events_and_without_duplicates(): void
    {
        $this->seed(FoundationSeeder::class);
        $implantacao = Implantacao::query()->where('slug', 'santa-rita')->firstOrFail();

        // DatabaseSeeder utiliza WithoutModelEvents. O implantacao_id nao
        // pode depender do callback creating de BelongsToImplantacao.
        Posto::withoutEvents(fn () => $this->seed(PostoSeeder::class));

        foreach (Posto::INICIAIS as $dados) {
            $this->assertDatabaseHas('postos', [
                'implantacao_id' => $implantacao->id,
                'codigo' => $dados['codigo'],
                'nome' => $dados['nome'],
                'tipo' => $dados['tipo'],
                'status' => 'ativo',
            ]);
        }

        $this->assertSame(count(Posto::INICIAIS), Posto::query()->count());

        // Semear novamente nao deve gerar um segundo posto de mesmo codigo.
        Posto::withoutEvents(fn () => $this->seed(PostoSeeder::class));
        $this->assertSame(count(Posto::INICIAIS), Posto::query()->count());
    }
}
