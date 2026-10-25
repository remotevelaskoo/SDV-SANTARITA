<?php

namespace Tests\Feature\Aj006;

use App\Acesso\Aplicacao\AutorizacoesAcesso;
use App\Acesso\Aplicacao\EnvioFacial;
use App\Integracoes\Adaptadores\Simulador\CenarioSimulador;
use App\Integracoes\Aplicacao\DiagnosticoEquipamento;
use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\EstadoSincronizacao;
use App\Integracoes\Dominio\Enums\OperacaoIntegracao;
use App\Livewire\PersonRegistration;
use App\Models\AutorizacaoAcesso;
use App\Models\CredencialAcesso;
use App\Models\CredencialSincronizacao;
use App\Models\Equipamento;
use App\Models\EquipamentoEventoRecebido;
use App\Models\Imovel;
use App\Models\OperacaoIntegracao as Operacao;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LogicException;
use Tests\Feature\Integracoes\Concerns\MontaCenarioIntegracao;
use Tests\TestCase;

/**
 * AJ-006, fatia 7 — turista com credencial de no máximo 24 horas, nova
 * autorização a cada visita e revogação nos terminais no vencimento.
 * Somente o simulador: nenhuma pessoa real vai a um terminal.
 */
class TuristaVisitaTest extends TestCase
{
    use MontaCenarioIntegracao, RefreshDatabase;

    private Equipamento $terminal;

    private User $operador;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-25 10:00:00');
        $this->prepararIntegracao();
        $this->terminal = $this->terminalAtivo(['nome' => 'Facial entrada']);
        $this->usarFilaReal();
        $this->operador = $this->usuarioCom('pessoas.gerenciar', 'facial.enviar');
        Imovel::factory()->create(['codigo' => 'SRA-T-01']);
        $this->relogioConferido();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->encerrarIntegracao();
        parent::tearDown();
    }

    public function test_visita_do_turista_vale_no_maximo_24_horas_e_vai_ao_terminal_com_inicio_e_fim(): void
    {
        $this->turista(fim: '2026-10-30')
            ->call('saveAndSend')
            ->assertSet('resultadoEnvio.variant', 'success')
            ->assertSee('Visita autorizada de 25/10/2026 10:00 até 26/10/2026 10:00');

        $autorizacao = AutorizacaoAcesso::query()->sole();
        $this->assertSame(AutorizacaoAcesso::TIPO_TURISTA, $autorizacao->tipo);
        $this->assertSame(24.0, $autorizacao->inicio->diffInHours($autorizacao->fim));

        $operacao = Operacao::query()->where('operacao', OperacaoIntegracao::SincronizarCredencial->value)->sole();
        $this->assertSame($autorizacao->inicio->format(DATE_ATOM), $operacao->payload['vigencia_inicio']);
        $this->assertSame($autorizacao->fim->format(DATE_ATOM), $operacao->payload['vigencia_fim']);
        // A retentativa depois do fim nunca recoloca a credencial no terminal.
        $this->assertTrue($operacao->expira_em->equalTo($autorizacao->fim));
    }

    public function test_fim_pedido_antes_de_24_horas_e_respeitado_e_inicio_futuro_tambem(): void
    {
        $pessoa = Pessoa::factory()->create(['status' => 'ativo']);
        $servico = app(AutorizacoesAcesso::class);

        $curta = $servico->registrarVisitaTurista($pessoa, null, 'Anfitrião', null, Carbon::parse('2026-10-25 18:00')->toImmutable());
        $this->assertSame('2026-10-25 18:00', $curta->fim->format('Y-m-d H:i'));

        $curta->forceFill(['status' => 'encerrada'])->save();
        $futura = $servico->registrarVisitaTurista($pessoa, null, 'Anfitrião', Carbon::parse('2026-10-27 00:00')->toImmutable(), null);
        $this->assertSame('2026-10-27 00:00', $futura->inicio->format('Y-m-d H:i'));
        $this->assertSame('2026-10-28 00:00', $futura->fim->format('Y-m-d H:i'));

        // Configuração acima de 24 horas é ignorada.
        config()->set('acesso.turista.validade_horas', 72);
        $this->assertSame(24, AutorizacoesAcesso::validadeTuristaHoras());
    }

    public function test_no_vencimento_a_credencial_e_revogada_e_a_pessoa_continua_no_sdv(): void
    {
        $this->turista()->call('saveAndSend');
        $pessoa = Pessoa::query()->sole();
        $this->assertCount(1, $this->simulador()->credenciaisGravadas($this->terminal->id));

        $this->travelTo(Carbon::parse('2026-10-26 10:01'));
        $this->artisan('acessos:processar-vencimentos')->assertSuccessful();

        $autorizacao = AutorizacaoAcesso::query()->sole();
        $this->assertSame('expirada', $autorizacao->status);
        $this->assertSame('expirada', CredencialAcesso::query()->sole()->status);
        $this->assertSame(EstadoSincronizacao::Removido, CredencialSincronizacao::query()->sole()->estado);
        $this->assertSame([], $this->simulador()->credenciaisGravadas($this->terminal->id));
        $this->assertSame('ativo', $pessoa->refresh()->status);
        $this->assertDatabaseHas('auditoria_eventos', ['action' => 'autorizacao_acesso_expirada', 'entity_id' => $autorizacao->id]);

        // Rodar de novo não cria outro pedido.
        $this->artisan('acessos:processar-vencimentos')->assertSuccessful();
        $this->assertSame(1, Operacao::query()->where('operacao', OperacaoIntegracao::RevogarCredencial->value)->count());

        // A pessoa e a autorização nunca são apagadas.
        $this->expectException(LogicException::class);
        $autorizacao->delete();
    }

    public function test_revogacao_sem_comunicacao_e_repetida_ate_o_terminal_confirmar(): void
    {
        config()->set('integracoes.outbox.max_tentativas', 1);
        $this->turista()->call('saveAndSend');

        $this->simulador()->definirCenario($this->terminal->id, CenarioSimulador::Indisponivel, OperacaoIntegracao::RevogarCredencial);
        $this->travelTo(Carbon::parse('2026-10-26 10:01'));
        $this->artisan('acessos:processar-vencimentos');
        $this->assertSame(EstadoSincronizacao::IntervencaoNecessaria, CredencialSincronizacao::query()->sole()->estado);

        // Antes do intervalo: não repete.
        $this->travelTo(Carbon::parse('2026-10-26 10:05'));
        $this->artisan('acessos:processar-vencimentos');
        $this->assertSame(1, Operacao::query()->where('operacao', OperacaoIntegracao::RevogarCredencial->value)->count());

        // Comunicação de volta e intervalo vencido: novo pedido, confirmado.
        $this->simulador()->definirCenario($this->terminal->id, CenarioSimulador::Sucesso, OperacaoIntegracao::RevogarCredencial);
        $this->travelTo(Carbon::parse('2026-10-26 10:20'));
        $this->artisan('acessos:processar-vencimentos');
        $this->assertSame(2, Operacao::query()->where('operacao', OperacaoIntegracao::RevogarCredencial->value)->count());
        $this->assertSame(EstadoSincronizacao::Removido, CredencialSincronizacao::query()->sole()->estado);
        $this->assertSame([], $this->simulador()->credenciaisGravadas($this->terminal->id));
    }

    public function test_nova_visita_reaproveita_a_pessoa_e_cria_nova_autorizacao_e_novo_envio(): void
    {
        $this->turista()->call('saveAndSend');
        $this->travelTo(Carbon::parse('2026-10-26 10:01'));
        $this->artisan('acessos:processar-vencimentos');

        $this->travelTo(Carbon::parse('2026-11-02 09:00'));
        $this->relogioConferido();
        Livewire::actingAs($this->operador)->test(PersonRegistration::class)
            ->set('accessType', 'tourist')
            ->set('document', '222.333.444-55')
            ->call('checkDocument')
            ->assertSee('Usar o cadastro existente e registrar nova visita')
            ->call('useExistingForVisit')
            ->assertSet('fullName', 'Turista Sintético')
            ->set('property', 'SRA-T-01')
            ->set('responsible', 'Anfitrião Sintético')
            ->set('startDate', '2026-11-02')
            ->set('endDate', '2026-11-03')
            ->set('terminalConfirmado', true)
            ->call('saveAndSend')
            ->assertSet('feedback.title', 'Nova visita registrada')
            ->assertSet('resultadoEnvio.variant', 'success');

        $this->assertSame(1, Pessoa::query()->count());
        $this->assertSame(['expirada', 'ativa'], AutorizacaoAcesso::query()->orderBy('inicio')->pluck('status')->all());
        $this->assertSame(2, CredencialAcesso::query()->count());
        $this->assertSame(2, Operacao::query()->where('operacao', OperacaoIntegracao::SincronizarCredencial->value)->count());
        $this->assertDatabaseHas('auditoria_eventos', ['action' => 'turista_nova_visita']);
    }

    public function test_visita_vigente_impede_outra_ao_mesmo_tempo(): void
    {
        $pessoa = Pessoa::factory()->create(['status' => 'ativo']);
        $servico = app(AutorizacoesAcesso::class);
        $servico->registrarVisitaTurista($pessoa, null, 'Anfitrião', null, null);

        $this->assertRegraViolada('visita_vigente', fn () => $servico->registrarVisitaTurista($pessoa, null, 'Anfitrião', null, null));
    }

    public function test_autorizacao_vencida_nao_e_enviada_e_envio_atrasado_nao_recoloca_a_credencial(): void
    {
        $this->turista()->call('activate');
        $autorizacao = AutorizacaoAcesso::query()->sole();
        $this->travelTo(Carbon::parse('2026-10-26 10:01'));

        $resultado = app(EnvioFacial::class)->enviar(Pessoa::query()->sole(), $this->terminal->id, $this->operador, $autorizacao);
        $this->assertSame('autorizacao_encerrada', $resultado->codigo);
        $this->assertSame(0, Operacao::query()->where('operacao', OperacaoIntegracao::SincronizarCredencial->value)->count());
    }

    public function test_relogio_e_fuso_sao_conferidos_antes_de_enviar_a_visita(): void
    {
        $this->turista()->call('activate');
        $pessoa = Pessoa::query()->sole();
        $envio = app(EnvioFacial::class);

        // Relógio do terminal divergente: bloqueia.
        $this->evento(divergencia: 600);
        $this->assertSame('relogio_terminal_divergente', $envio->enviar($pessoa, $this->terminal->id, $this->operador)->codigo);

        // Fuso do servidor diferente do da portaria: bloqueia.
        $this->relogioConferido();
        config()->set('acesso.fuso_operacional', 'America/Manaus');
        $this->assertSame('fuso_servidor_divergente', $envio->enviar($pessoa, $this->terminal->id, $this->operador)->codigo);
        config()->set('acesso.fuso_operacional', 'America/Sao_Paulo');

        // Sem evento recente: envia com aviso; sem validade nativa: avisa que a remoção depende do SDV.
        EquipamentoEventoRecebido::query()->delete();
        $this->simulador()->definirCapacidades($this->terminal->id, array_values(array_filter(Capacidade::cases(), fn ($c) => $c !== Capacidade::ValidadeNativa)));
        $this->processar(app(DiagnosticoEquipamento::class)->consultarCapacidades($this->terminal->refresh()));
        $resultado = $envio->enviar($pessoa, $this->terminal->id, $this->operador);
        $this->assertTrue($resultado->aceito());
        $this->assertCount(2, $resultado->avisos);
        $this->assertStringContainsString('não aplica sozinho o fim da validade', $resultado->avisos[1]);
        $this->assertDatabaseHas('auditoria_eventos', ['action' => 'facial_envio_recusado', 'reason_code' => 'relogio_terminal_divergente']);
    }

    public function test_encerrar_a_visita_antes_do_fim_revoga_nos_terminais_com_motivo(): void
    {
        $this->turista()->call('saveAndSend');
        $autorizacao = AutorizacaoAcesso::query()->sole();
        $gestor = $this->usuarioCom('autorizacoes.gerenciar');

        $this->assertRegraViolada('motivo_obrigatorio', fn () => app(AutorizacoesAcesso::class)->encerrar($autorizacao, 'x', $gestor));
        app(AutorizacoesAcesso::class)->encerrar($autorizacao, 'Saída antecipada do hóspede', $gestor);

        $this->assertSame('encerrada', $autorizacao->refresh()->status);
        $this->assertSame('revogada', CredencialAcesso::query()->sole()->status);
        $this->assertSame(EstadoSincronizacao::Removido, CredencialSincronizacao::query()->sole()->estado);
    }

    private function turista(string $fim = '2026-10-26'): Testable
    {
        return Livewire::actingAs($this->operador)->test(PersonRegistration::class)
            ->set('accessType', 'tourist')
            ->set('fullName', 'Turista Sintético')
            ->set('document', '222.333.444-55')
            ->set('birthDate', '1990-05-14')
            ->set('phone', '(24) 99988-7766')
            ->set('property', 'SRA-T-01')
            ->set('responsible', 'Anfitrião Sintético')
            ->set('startDate', '2026-10-25')
            ->set('endDate', $fim)
            ->set('terminalConfirmado', true);
    }

    private function relogioConferido(): void
    {
        EquipamentoEventoRecebido::query()->delete();
        $this->evento(divergencia: null);
    }

    private function evento(?int $divergencia): void
    {
        EquipamentoEventoRecebido::query()->create([
            'equipamento_id' => $this->terminal->id,
            'id_externo_evento' => 'SIM-'.Str::random(8),
            'hash_payload' => str_repeat('a', 64),
            'tipo' => 'acesso',
            'ocorrido_no_equipamento_em' => now(),
            'recebido_em' => now(),
            'divergencia_relogio_segundos' => $divergencia,
        ]);
    }
}
