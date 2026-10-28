<?php

namespace Tests\Feature\Aj006;

use App\Livewire\PreRegistrationReview;
use App\Models\Implantacao;
use App\Models\PreRegistration;
use App\Services\PrivateFileService;
use App\Support\ImplantacaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\OperadorComPermissoes;
use Tests\TestCase;

/** SDV-OPE-020 §13 — hierarquia simplificada da análise do pré-cadastro. Só dados sintéticos. */
class AnaliseSimplificadaTest extends TestCase
{
    use OperadorComPermissoes, RefreshDatabase;

    private const DECISOR = ['pre-registro.analisar', 'pre-registro.editar', 'pre-registro.aprovar', 'pre-registro.rejeitar', 'pre-registro.solicitar-correcao'];

    protected function setUp(): void
    {
        parent::setUp();
        ImplantacaoContext::setCurrentForTesting(Implantacao::factory()->create());
        Storage::fake(PrivateFileService::DISK);
    }

    protected function tearDown(): void
    {
        ImplantacaoContext::forgetCurrent();
        parent::tearDown();
    }

    public function test_apresenta_a_hierarquia_aprovada_na_ordem(): void
    {
        $registro = $this->registroComLeitura();

        Livewire::actingAs($this->operador(...self::DECISOR))
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->assertSeeInOrder([
                '← Voltar à fila de pré-cadastros',
                'Pessoa Ficticia de Teste',
                'Pendências para conferir',
                'Conferência visual protegida',
                'Dados essenciais',
                'Leitura automática do documento',
                'Ver mais detalhes',
                'Comparação completa da leitura',
                'Histórico',
                'Decisão',
                'Aprovar pré-cadastro',
            ]);
    }

    public function test_alertas_ficam_agrupados_em_um_unico_resumo(): void
    {
        $registro = PreRegistration::factory()->create(['alert' => 'Aviso sintético de teste']);

        $html = Livewire::actingAs($this->operador('pre-registro.analisar'))
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->assertSee('Pendências para conferir')
            ->assertSee('Aviso sintético de teste')
            ->assertSee('Sem selfie')
            ->assertSee('Documento não enviado')
            ->html();

        $this->assertSame(1, substr_count($html, 'pre-cadastro-analise__pendencias'));
        $this->assertStringNotContainsString('class="ui-alert', $this->entre($html, 'pre-cadastro-analise__pendencias', '</section>'));
        $this->assertMatchesRegularExpression('/pre-cadastro-analise__contador">\s*3\s*</', $html);
    }

    public function test_sem_pendencias_mostra_resumo_compacto(): void
    {
        $registro = PreRegistration::factory()->create([
            'name' => 'Pessoa Ficticia de Teste',
            'document' => '000.000.001-91',
            'birth_date' => '1990-01-01',
            'alert' => null,
            'period_end' => now()->addDay(),
        ]);
        app(PrivateFileService::class)->storePreRegistrationFiles($registro, [
            'selfie' => UploadedFile::fake()->image('selfie.jpg', 480, 640),
            'documento' => UploadedFile::fake()->image('frente.jpg', 1200, 800),
        ]);
        $this->leitura($registro, confiancaNascimento: 0.95, cpfConfirmado: '000.000.001-91');

        Livewire::actingAs($this->operador('pre-registro.analisar'))
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->assertSee('Nenhuma pendência encontrada')
            ->assertSee('Os campos lidos conferem com os dados confirmados');
    }

    public function test_divergencias_do_ocr_aparecem_primeiro_e_a_comparacao_completa_fica_recolhida(): void
    {
        $registro = $this->registroComLeitura();

        $html = Livewire::actingAs($this->operador('pre-registro.analisar'))
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->assertSee('2 campos para conferir')
            ->html();

        $destaque = $this->entre($html, 'aria-label="Campos para conferir"', '</ul>');
        $this->assertStringContainsString('CPF', $destaque);
        $this->assertStringContainsString('Data de nascimento', $destaque);
        $this->assertStringContainsString('41%', $destaque);
        $this->assertStringNotContainsString('Nome', $destaque, 'campo que confere não entra no destaque');
        $this->assertStringContainsString('***.***.001-**', $destaque, 'documento continua mascarado no destaque');
        $this->assertStringNotContainsString('000.000.001-91', $html);

        $this->assertRecolhidos($html);
    }

    public function test_miniaturas_em_grade_compacta_e_ampliacao_protegida(): void
    {
        $registro = PreRegistration::factory()->create();
        $arquivos = app(PrivateFileService::class)->storePreRegistrationFiles($registro, [
            'selfie' => UploadedFile::fake()->image('selfie.jpg', 480, 640),
            'documento' => UploadedFile::fake()->image('frente.jpg', 1200, 800),
            'documento_verso' => UploadedFile::fake()->image('verso.jpg', 1200, 800),
        ]);

        $html = Livewire::actingAs($this->operador('pre-registro.analisar', 'arquivos.sensiveis.visualizar'))
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->assertSee('Exibir imagens para conferência')
            ->assertSee('Ampliar Selfie', escape: false)
            ->assertSee('Download não é oferecido')
            ->html();

        $galeria = $this->entre($html, 'aria-label="Miniaturas das evidências"', '</ul>');
        $this->assertSame(4, substr_count($galeria, 'pre-cadastro-analise__figura'));
        $this->assertSame(1, substr_count($galeria, 'is-ausente'), 'foto recortada ausente vira cartão compacto');
        foreach ($arquivos as $arquivo) {
            $this->assertStringContainsString($arquivo->id, $galeria);
            $this->assertStringNotContainsString('src="'.route('protected-files.show', $arquivo), $html);
        }
    }

    public function test_motivo_so_aparece_depois_de_escolher_a_acao(): void
    {
        $registro = PreRegistration::factory()->create(['status' => 'aguardando']);

        $teste = Livewire::actingAs($this->operador(...self::DECISOR))
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->assertSee('Editar dados')
            ->assertSee('Solicitar correção')
            ->assertSee('Rejeitar')
            ->assertSee('Aprovar pré-cadastro')
            ->assertDontSee('Motivo da rejeição')
            ->assertDontSee('Pedir correção de')
            ->call('chooseDecision', 'rejeitar')
            ->assertSee('Motivo da rejeição')
            ->assertSee('Confirmar rejeição')
            ->assertDontSee('Pedir correção de')
            ->call('cancelDecision')
            ->assertDontSee('Motivo da rejeição')
            ->call('chooseDecision', 'corrigir')
            ->assertSee('Pedir correção de')
            ->assertSee('Confirmar solicitação de correção')
            ->assertDontSee('Motivo da rejeição');

        $teste->set('correctionItems', 'documento')->call('requestCorrection')->assertHasNoErrors();
        $this->assertSame('correcao', $registro->fresh()->status);
    }

    public function test_escolher_acao_respeita_a_permissao_especifica(): void
    {
        $registro = PreRegistration::factory()->create(['status' => 'aguardando']);

        Livewire::actingAs($this->operador('pre-registro.analisar', 'pre-registro.aprovar'))
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->assertDontSee('Rejeitar')
            ->call('chooseDecision', 'rejeitar')
            ->assertForbidden();

        Livewire::actingAs($this->operador('pre-registro.analisar', 'pre-registro.aprovar'))
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->call('chooseDecision', 'corrigir')
            ->assertForbidden();

        $this->assertSame('aguardando', $registro->fresh()->status);
    }

    public function test_dados_essenciais_em_destaque_e_secundarios_recolhidos(): void
    {
        $registro = PreRegistration::factory()->create([
            'email' => 'pessoa.ficticia@example.com',
            'vehicle_plate' => 'TST1A23',
        ]);

        $html = Livewire::actingAs($this->operador('pre-registro.analisar'))
            ->test(PreRegistrationReview::class, ['preRegistration' => $registro->id])
            ->html();

        $essenciais = $this->entre($html, 'pre-cadastro-analise__essenciais', '</dl>');
        foreach (['Nome completo', 'Documento', 'Data de nascimento', 'Telefone', 'Tipo de acesso', 'Responsável', 'Período'] as $rotulo) {
            $this->assertStringContainsString($rotulo, $essenciais);
        }
        $this->assertStringNotContainsString('pessoa.ficticia@example.com', $essenciais);
        $this->assertStringContainsString('pessoa.ficticia@example.com', $this->entre($html, '<summary>Ver mais detalhes</summary>', '</details>'));
    }

    private function registroComLeitura(): PreRegistration
    {
        $registro = PreRegistration::factory()->create([
            'name' => 'Pessoa Ficticia de Teste',
            'document' => '000.000.001-91',
            'birth_date' => '1990-01-01',
        ]);
        $this->leitura($registro, confiancaNascimento: 0.41, cpfConfirmado: '000.000.001-00');

        return $registro;
    }

    private function leitura(PreRegistration $registro, float $confiancaNascimento, string $cpfConfirmado): void
    {
        $registro->documentExtractions()->create([
            'document_type' => 'cnh',
            'engine' => 'teste',
            'model_version' => 'sintetico',
            'status' => 'confirmed',
            'extracted_fields' => ['name' => $registro->name, 'cpf' => '000.000.001-91', 'birth_date' => $registro->birth_date?->format('Y-m-d')],
            'field_confidences' => ['name' => 0.97, 'cpf' => 0.96, 'birth_date' => $confiancaNascimento],
            'confirmed_fields' => ['name' => $registro->name, 'cpf' => $cpfConfirmado, 'birth_date' => $registro->birth_date?->format('Y-m-d')],
            'processed_at' => now(),
            'confirmed_at' => now(),
        ]);
    }

    private function assertRecolhidos(string $html): void
    {
        foreach (['Ver mais detalhes', 'Comparação completa da leitura', 'Histórico'] as $resumo) {
            $this->assertMatchesRegularExpression('/<details class="pre-cadastro-analise__recolhido">\s*<summary>'.preg_quote($resumo, '/').'<\/summary>/u', $html, "{$resumo} deve começar recolhido");
        }
        $this->assertStringNotContainsString('<details class="pre-cadastro-analise__recolhido" open', $html);
    }

    private function entre(string $html, string $inicio, string $fim): string
    {
        $posicao = strpos($html, $inicio);
        $this->assertNotFalse($posicao, "Trecho não encontrado: {$inicio}");
        $resto = substr($html, $posicao);

        return substr($resto, 0, (int) strpos($resto, $fim));
    }
}
