<?php

namespace Tests\Feature\Integracoes\Concerns;

use App\Integracoes\Adaptadores\Simulador\SimuladorEquipamento;
use App\Integracoes\Aplicacao\CadastroEquipamentos;
use App\Integracoes\Aplicacao\DiagnosticoEquipamento;
use App\Integracoes\Aplicacao\ProcessadorOperacoes;
use App\Integracoes\Dominio\Dados\CredencialParaSincronizar;
use App\Integracoes\Dominio\Enums\Direcao;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Integracoes\Dominio\Enums\StatusPontoAcesso;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\Equipamento;
use App\Models\Implantacao;
use App\Models\OperacaoIntegracao;
use App\Models\Perfil;
use App\Models\Permissao;
use App\Models\PontoAcesso;
use App\Models\User;
use App\Models\UsuarioPerfil;
use App\Support\ImplantacaoContext;
use DateTimeImmutable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

trait MontaCenarioIntegracao
{
    protected Implantacao $implantacao;

    private mixed $filaReal = null;

    protected function prepararIntegracao(): void
    {
        $this->implantacao = Implantacao::factory()->create();
        ImplantacaoContext::setCurrentForTesting($this->implantacao);
        $this->filaReal = app('queue');
        Queue::fake();
        $this->simulador()->reiniciar();
        putenv('SDV_TESTE_TERMINAL_SENHA=Senha-Terminal-Teste-987');
    }

    protected function encerrarIntegracao(): void
    {
        putenv('SDV_TESTE_TERMINAL_SENHA');
        ImplantacaoContext::forgetCurrent();
    }

    protected function assertRegraViolada(string $codigo, callable $acao): void
    {
        try {
            $acao();
        } catch (RegraIntegracaoViolada $e) {
            $this->assertSame($codigo, $e->codigo, $e->getMessage());

            return;
        }

        $this->fail("Era esperada a recusa '{$codigo}'.");
    }

    /** Desfaz o Queue::fake(): com QUEUE_CONNECTION=sync o job roda de verdade após o commit. */
    protected function usarFilaReal(): void
    {
        Queue::swap($this->filaReal);
    }

    protected function usuarioCom(string ...$permissoes): User
    {
        $usuario = User::factory()->create();
        $perfil = Perfil::factory()->create();

        foreach ($permissoes as $chave) {
            $permissao = Permissao::query()->firstOrCreate(['chave' => $chave], ['modulo' => 'integracoes', 'descricao' => $chave]);
            $perfil->permissoes()->attach($permissao->id, [
                'id' => (string) Str::uuid7(),
                'implantacao_id' => ImplantacaoContext::current()->id,
            ]);
        }

        UsuarioPerfil::factory()->for($usuario)->for($perfil)->create();

        return $usuario;
    }

    /** Terminal do simulador em homologação na bancada, com capacidades verificadas. */
    protected function terminalEmHomologacao(array $dados = []): Equipamento
    {
        $ponto = $this->novoPonto(['tipo' => 'bancada', 'codigo' => 'BANCADA-'.Str::upper(Str::random(4)), 'nome' => 'Bancada de testes']);
        $equipamento = $this->novoEquipamento($dados);
        $this->cadastro()->vincularAoPonto($equipamento, $ponto);
        $this->cadastro()->definirSenhaTecnica($equipamento, 'admin', 'Senha-Cifrada-Teste-321');
        $this->processar(app(DiagnosticoEquipamento::class)->consultarCapacidades($equipamento));

        return $this->cadastro()->alterarStatus($equipamento->refresh(), StatusEquipamento::EmHomologacao, 'bancada');
    }

    protected function simulador(): SimuladorEquipamento
    {
        return app(SimuladorEquipamento::class);
    }

    protected function cadastro(): CadastroEquipamentos
    {
        return app(CadastroEquipamentos::class);
    }

    protected function processar(OperacaoIntegracao $operacao): OperacaoIntegracao
    {
        app(ProcessadorOperacoes::class)->processar($operacao->id);

        return $operacao->refresh();
    }

    protected function novoPonto(array $dados = []): PontoAcesso
    {
        return $this->cadastro()->registrarPontoAcesso(array_merge([
            'codigo' => 'CANCELA-'.Str::upper(Str::random(5)),
            'nome' => 'Cancela principal',
            'tipo' => 'cancela',
            'direcao_suportada' => 'bidirecional',
        ], $dados));
    }

    protected function novoEquipamento(array $dados = []): Equipamento
    {
        return $this->cadastro()->registrarEquipamento(array_merge([
            'nome' => 'Terminal facial portaria',
            'tipo' => 'terminal_facial',
            'fabricante' => 'Hikvision',
            'modelo' => 'DS-K1T673DX-BR',
            'numero_serie' => 'SN-'.Str::upper(Str::random(8)),
            'endereco_rede' => '192.168.10.20',
            'porta_rede' => 443,
            'protocolo' => 'isapi',
            'firmware_versao' => 'V-TESTE-1',
            'adaptador' => 'simulador',
            'direcao' => 'entrada',
        ], $dados));
    }

    /** Terminal do simulador vinculado a um ponto ativo, com capacidades verificadas e ativo. */
    protected function terminalAtivo(array $dados = []): Equipamento
    {
        $ponto = $this->novoPonto();
        $this->cadastro()->alterarStatusPonto($ponto, StatusPontoAcesso::Ativo, 'bancada');
        $equipamento = $this->novoEquipamento($dados);
        $this->cadastro()->vincularAoPonto($equipamento, $ponto);
        $this->cadastro()->definirCredencialTecnica($equipamento, 'env:SDV_TESTE_TERMINAL_SENHA', 'admin');
        $this->processar(app(DiagnosticoEquipamento::class)->consultarCapacidades($equipamento));

        return $this->cadastro()->alterarStatus($equipamento->refresh(), StatusEquipamento::Ativo, 'bancada');
    }

    protected function credencial(array $sobrescrever = []): CredencialParaSincronizar
    {
        return new CredencialParaSincronizar(
            credencialId: $sobrescrever['credencialId'] ?? (string) Str::uuid7(),
            tipo: $sobrescrever['tipo'] ?? 'cartao',
            titularReferencia: (string) Str::uuid7(),
            nomeExibicao: 'Pessoa Sintética',
            vigenciaInicio: new DateTimeImmutable('-1 day'),
            vigenciaFim: $sobrescrever['vigenciaFim'] ?? new DateTimeImmutable('+30 days'),
            direcao: Direcao::Entrada,
        );
    }
}
