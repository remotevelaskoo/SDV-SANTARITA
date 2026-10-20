<?php

namespace App\Integracoes\Adaptadores\Simulador;

use App\Integracoes\Dominio\Contratos\PortaEquipamentoAcesso;
use App\Integracoes\Dominio\Dados\CapacidadesDeclaradas;
use App\Integracoes\Dominio\Dados\ComandoAbertura;
use App\Integracoes\Dominio\Dados\ContextoEquipamento;
use App\Integracoes\Dominio\Dados\CredencialParaSincronizar;
use App\Integracoes\Dominio\Dados\EventoEquipamento;
use App\Integracoes\Dominio\Dados\ImagemCapturada;
use App\Integracoes\Dominio\Dados\ResultadoColetaEventos;
use App\Integracoes\Dominio\Dados\ResultadoOperacao;
use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\OperacaoIntegracao;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;
use DateTimeImmutable;
use RuntimeException;

/**
 * Simulador contratual de terminal de ponto de acesso (ADR-007 §14).
 *
 * Mantém em memória, por equipamento, as credenciais "gravadas", os
 * eventos pendentes e as aberturas executadas, e reproduz os cenários de
 * sucesso, recusa, timeout, callback tardio, duplicidade, indisponibilidade,
 * payload inválido, capacidade ausente, confirmação desconhecida, exceção
 * interna, credencial inválida, certificado inválido, captura disponível ou
 * indisponível e comando somente aceito. Serve a testes e desenvolvimento; não substitui homologação
 * com o terminal real e só é aceito fora de produção.
 *
 * O estado é do processo (registrado como singleton); não há persistência.
 */
class SimuladorEquipamento implements PortaEquipamentoAcesso
{
    public const CODIGO = 'simulador';

    public const VERSAO_CONTRATO = '1.0.0';

    /** @var array<string, array<string, CenarioSimulador>> equipamento => operação|'*' => cenário */
    private array $cenarios = [];

    /** @var array<string, list<Capacidade>> */
    private array $capacidades = [];

    /** @var array<string, array<string, string>> equipamento => credencial => id externo */
    private array $credenciais = [];

    /** @var array<string, list<array<string, mixed>>> eventos brutos pendentes */
    private array $eventos = [];

    /** @var array<string, array<string, int>> equipamento => comando => aberturas físicas */
    private array $aberturas = [];

    /** @var array<string, int> */
    private array $chamadas = [];

    /** @var array<string, CenarioSimulador> equipamento => cenário da captura de imagem */
    private array $cenariosCaptura = [];

    /** PNG 1x1 sintético: o simulador nunca devolve imagem de pessoa. */
    private const IMAGEM_SINTETICA = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    public function codigo(): string
    {
        return self::CODIGO;
    }

    public function versaoContrato(): string
    {
        return self::VERSAO_CONTRATO;
    }

    // ---- Configuração do cenário -----------------------------------------

    public function definirCenario(string $equipamentoId, CenarioSimulador $cenario, ?OperacaoIntegracao $operacao = null): void
    {
        $this->cenarios[$equipamentoId][$operacao?->value ?? '*'] = $cenario;
    }

    public function definirCenarioCaptura(string $equipamentoId, CenarioSimulador $cenario): void
    {
        $this->cenariosCaptura[$equipamentoId] = $cenario;
    }

    /** @param  list<Capacidade>  $capacidades */
    public function definirCapacidades(string $equipamentoId, array $capacidades): void
    {
        $this->capacidades[$equipamentoId] = $capacidades;
    }

    /** Registra um evento que o terminal devolverá na próxima coleta. */
    public function registrarEventoNoTerminal(string $equipamentoId, array $eventoBruto): void
    {
        $this->eventos[$equipamentoId][] = $eventoBruto;
    }

    /** @return array<string, string> credencial => id externo */
    public function credenciaisGravadas(string $equipamentoId): array
    {
        return $this->credenciais[$equipamentoId] ?? [];
    }

    public function totalAberturasFisicas(string $equipamentoId): int
    {
        return array_sum($this->aberturas[$equipamentoId] ?? []);
    }

    public function chamadas(string $equipamentoId, OperacaoIntegracao $operacao): int
    {
        return $this->chamadas[$equipamentoId.'|'.$operacao->value] ?? 0;
    }

    public function reiniciar(): void
    {
        $this->cenarios = $this->capacidades = $this->credenciais = $this->eventos = $this->aberturas = $this->chamadas = $this->cenariosCaptura = [];
    }

    // ---- Porta ------------------------------------------------------------

    public function testarConexao(ContextoEquipamento $equipamento): ResultadoOperacao
    {
        return $this->executar($equipamento, OperacaoIntegracao::TestarConexao, null, function () use ($equipamento) {
            if ($equipamento->segredo() === null) {
                return new ResultadoOperacao(ResultadoEquipamento::Recusado, 'autenticacao_recusada', 'Credencial técnica ausente ou recusada.');
            }

            return ResultadoOperacao::confirmado(dados: ['firmware' => $equipamento->firmwareVersao]);
        });
    }

    public function consultarCapacidades(ContextoEquipamento $equipamento): CapacidadesDeclaradas
    {
        $this->contar($equipamento, OperacaoIntegracao::ConsultarCapacidades);
        $cenario = $this->cenario($equipamento->equipamentoId, OperacaoIntegracao::ConsultarCapacidades);

        if (in_array($cenario, [CenarioSimulador::Indisponivel, CenarioSimulador::Timeout], true)) {
            return new CapacidadesDeclaradas(
                $cenario === CenarioSimulador::Timeout ? ResultadoEquipamento::ConfirmacaoDesconhecida : ResultadoEquipamento::Indisponivel,
                self::VERSAO_CONTRATO,
                $equipamento->firmwareVersao,
                [],
            );
        }

        $suportadas = array_values($this->capacidades[$equipamento->equipamentoId] ?? Capacidade::cases());

        $motivos = [];
        foreach (Capacidade::cases() as $capacidade) {
            if (! in_array($capacidade, $suportadas, true)) {
                $motivos[$capacidade->value] = 'não oferecida pelo simulador';
            }
        }

        return new CapacidadesDeclaradas(
            ResultadoEquipamento::Confirmado,
            self::VERSAO_CONTRATO,
            $equipamento->firmwareVersao,
            $suportadas,
            $motivos,
            consultadoNoEquipamento: true,
        );
    }

    public function sincronizarCredencial(ContextoEquipamento $equipamento, CredencialParaSincronizar $credencial): ResultadoOperacao
    {
        return $this->executar($equipamento, OperacaoIntegracao::SincronizarCredencial, Capacidade::SincronizarCredencial, function () use ($equipamento, $credencial) {
            $id = $this->gravar($equipamento->equipamentoId, $credencial->credencialId);

            return ResultadoOperacao::confirmado($id);
        }, efeitoTardio: fn () => $this->gravar($equipamento->equipamentoId, $credencial->credencialId));
    }

    public function revogarCredencial(ContextoEquipamento $equipamento, string $credencialId, ?string $idExterno): ResultadoOperacao
    {
        return $this->executar($equipamento, OperacaoIntegracao::RevogarCredencial, Capacidade::RevogarCredencial, function () use ($equipamento, $credencialId) {
            unset($this->credenciais[$equipamento->equipamentoId][$credencialId]);

            return ResultadoOperacao::confirmado();
        }, efeitoTardio: function () use ($equipamento, $credencialId) {
            unset($this->credenciais[$equipamento->equipamentoId][$credencialId]);
        });
    }

    public function consultarSincronizacao(ContextoEquipamento $equipamento, string $credencialId, ?string $idExterno): ResultadoOperacao
    {
        return $this->executar($equipamento, OperacaoIntegracao::ConsultarSincronizacao, Capacidade::ConsultarSincronizacao, function () use ($equipamento, $credencialId) {
            $gravado = $this->credenciais[$equipamento->equipamentoId][$credencialId] ?? null;

            return ResultadoOperacao::confirmado($gravado, ['presente' => $gravado !== null]);
        });
    }

    public function coletarEventos(ContextoEquipamento $equipamento, ?string $cursor): ResultadoColetaEventos
    {
        $this->contar($equipamento, OperacaoIntegracao::ColetarEventos);
        $cenario = $this->cenario($equipamento->equipamentoId, OperacaoIntegracao::ColetarEventos);

        $falha = $this->resultadoDeFalha($cenario, $equipamento, Capacidade::ColetarEventos);
        if ($falha !== null && $cenario !== CenarioSimulador::PayloadInvalido) {
            return new ResultadoColetaEventos($falha->resultado, mensagem: $falha->mensagem);
        }

        $brutos = $this->eventos[$equipamento->equipamentoId] ?? [];
        $this->eventos[$equipamento->equipamentoId] = [];

        if ($cenario === CenarioSimulador::Duplicidade) {
            $brutos = [...$brutos, ...$brutos];
        }

        $eventos = [];
        $invalidos = 0;
        foreach ($brutos as $bruto) {
            $evento = $this->traduzirEvento($bruto, $cenario === CenarioSimulador::PayloadInvalido);
            $evento === null ? $invalidos++ : $eventos[] = $evento;
        }

        return new ResultadoColetaEventos(
            ResultadoEquipamento::Confirmado,
            $eventos,
            cursor: (string) ((int) $cursor + count($brutos)),
            descartadosInvalidos: $invalidos,
        );
    }

    public function solicitarAbertura(ContextoEquipamento $equipamento, ComandoAbertura $comando): ResultadoOperacao
    {
        return $this->executar($equipamento, OperacaoIntegracao::AberturaRemota, Capacidade::AberturaRemota, function () use ($equipamento, $comando) {
            $this->abrir($equipamento->equipamentoId, $comando->comandoId);

            if ($this->cenario($equipamento->equipamentoId, OperacaoIntegracao::AberturaRemota) === CenarioSimulador::Aceito) {
                return new ResultadoOperacao(ResultadoEquipamento::Aceito, 'comando_aceito', 'O terminal aceitou o comando; abertura física não comprovada.');
            }

            return ResultadoOperacao::confirmado(dados: ['comando' => $comando->comandoId]);
        }, efeitoTardio: fn () => $this->abrir($equipamento->equipamentoId, $comando->comandoId));
    }

    public function capturarImagem(ContextoEquipamento $equipamento): ImagemCapturada
    {
        $cenario = $this->cenariosCaptura[$equipamento->equipamentoId] ?? $this->cenario($equipamento->equipamentoId, OperacaoIntegracao::TestarConexao);

        if ($cenario === CenarioSimulador::CapturaIndisponivel) {
            return ImagemCapturada::falha(ResultadoEquipamento::FalhaTecnica, 'imagem_indisponivel', 'A câmera do terminal não devolveu imagem.');
        }

        $falha = $this->resultadoDeFalha($cenario, $equipamento, Capacidade::CapturarImagem);
        if ($falha !== null) {
            return ImagemCapturada::falha($falha->resultado, (string) $falha->codigo, (string) $falha->mensagem);
        }

        return ImagemCapturada::capturada((string) base64_decode(self::IMAGEM_SINTETICA, true), 'image/png', new DateTimeImmutable);
    }

    public function consultarResultadoComando(ContextoEquipamento $equipamento, string $comandoId): ResultadoOperacao
    {
        return $this->executar($equipamento, OperacaoIntegracao::ConsultarResultadoComando, Capacidade::ConsultarResultadoComando, function () use ($equipamento, $comandoId) {
            $executado = ($this->aberturas[$equipamento->equipamentoId][$comandoId] ?? 0) > 0;

            return $executado
                ? ResultadoOperacao::confirmado(dados: ['executado' => true])
                : new ResultadoOperacao(ResultadoEquipamento::Recusado, 'comando_nao_executado', 'O terminal não registra execução do comando.', dados: ['executado' => false]);
        });
    }

    // ---- Internos ---------------------------------------------------------

    /**
     * @param  callable(): ResultadoOperacao  $sucesso
     * @param  (callable(): mixed)|null  $efeitoTardio  efeito aplicado no terminal quando a resposta se perde
     */
    private function executar(ContextoEquipamento $equipamento, OperacaoIntegracao $operacao, ?Capacidade $capacidade, callable $sucesso, ?callable $efeitoTardio = null): ResultadoOperacao
    {
        $this->contar($equipamento, $operacao);
        $cenario = $this->cenario($equipamento->equipamentoId, $operacao);

        if ($cenario === CenarioSimulador::CallbackTardio) {
            // O terminal executou, mas a resposta não chegou a tempo.
            if ($efeitoTardio !== null) {
                $efeitoTardio();
            }

            return ResultadoOperacao::desconhecido('timeout', 'Tempo esgotado aguardando o terminal.');
        }

        if ($cenario === CenarioSimulador::Excecao) {
            throw new RuntimeException('Falha interna simulada com senha=SIMULADA-NAO-DEVE-VAZAR');
        }

        return $this->resultadoDeFalha($cenario, $equipamento, $capacidade) ?? $sucesso();
    }

    private function resultadoDeFalha(CenarioSimulador $cenario, ContextoEquipamento $equipamento, ?Capacidade $capacidade): ?ResultadoOperacao
    {
        if ($capacidade !== null && ! in_array($capacidade, $this->capacidades[$equipamento->equipamentoId] ?? Capacidade::cases(), true)) {
            return ResultadoOperacao::capacidadeAusente($capacidade, 'não oferecida pelo simulador');
        }

        return match ($cenario) {
            CenarioSimulador::Recusa => new ResultadoOperacao(ResultadoEquipamento::Recusado, 'recusado_pelo_terminal', 'O terminal recusou a operação.'),
            CenarioSimulador::Timeout, CenarioSimulador::ConfirmacaoDesconhecida => ResultadoOperacao::desconhecido('timeout', 'Tempo esgotado aguardando o terminal.'),
            CenarioSimulador::Indisponivel => new ResultadoOperacao(ResultadoEquipamento::Indisponivel, 'conexao_recusada', 'Terminal inalcançável na rede.'),
            CenarioSimulador::PayloadInvalido => new ResultadoOperacao(ResultadoEquipamento::FalhaTecnica, 'resposta_invalida', 'Resposta do terminal fora do contrato.'),
            CenarioSimulador::CapacidadeAusente => ResultadoOperacao::capacidadeAusente($capacidade ?? Capacidade::TestarConexao, 'cenário de capacidade ausente'),
            CenarioSimulador::CredencialInvalida => new ResultadoOperacao(ResultadoEquipamento::Recusado, 'autenticacao_recusada', 'O terminal recusou o usuário ou a senha técnica.'),
            CenarioSimulador::CertificadoInvalido => new ResultadoOperacao(ResultadoEquipamento::FalhaTecnica, 'certificado_invalido', 'O certificado do terminal não é confiável.'),
            default => null,
        };
    }

    private function cenario(string $equipamentoId, OperacaoIntegracao $operacao): CenarioSimulador
    {
        return $this->cenarios[$equipamentoId][$operacao->value]
            ?? $this->cenarios[$equipamentoId]['*']
            ?? CenarioSimulador::Sucesso;
    }

    private function contar(ContextoEquipamento $equipamento, OperacaoIntegracao $operacao): void
    {
        $chave = $equipamento->equipamentoId.'|'.$operacao->value;
        $this->chamadas[$chave] = ($this->chamadas[$chave] ?? 0) + 1;
    }

    private function gravar(string $equipamentoId, string $credencialId): string
    {
        return $this->credenciais[$equipamentoId][$credencialId] ??= 'SIM-'.strtoupper(substr(hash('sha256', $equipamentoId.$credencialId), 0, 12));
    }

    private function abrir(string $equipamentoId, string $comandoId): void
    {
        $this->aberturas[$equipamentoId][$comandoId] = ($this->aberturas[$equipamentoId][$comandoId] ?? 0) + 1;
    }

    private function traduzirEvento(array $bruto, bool $forcarInvalido): ?EventoEquipamento
    {
        if ($forcarInvalido || ! isset($bruto['serial'], $bruto['tipo']) || ! is_scalar($bruto['serial'])) {
            return null;
        }

        try {
            $ocorrido = isset($bruto['instante']) ? new DateTimeImmutable((string) $bruto['instante']) : null;
        } catch (\Exception) {
            return null;
        }

        return new EventoEquipamento(
            idExterno: (string) $bruto['serial'],
            tipo: (string) $bruto['tipo'],
            ocorridoEm: $ocorrido,
            direcao: isset($bruto['direcao']) ? (string) $bruto['direcao'] : null,
            resultado: isset($bruto['resultado']) ? (string) $bruto['resultado'] : null,
            referenciaCredencialExterna: isset($bruto['credencial']) ? (string) $bruto['credencial'] : null,
        );
    }
}
