<?php

namespace App\Integracoes\Aplicacao;

use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\OperadorTurno;
use App\Models\PontoAcesso;
use App\Models\Posto;
use App\Models\PostoPontoAcesso;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * Postos da portaria e pontos que cada um atende (ADR-017). Qualquer
 * operador assume qualquer posto ativo (SDV-OPE-018 §14), então não há
 * designação por posto. Nada é apagado: vínculos são encerrados com data.
 */
class CadastroPostos
{
    public function __construct(private AuditService $audit) {}

    public function criarPosto(string $codigo, string $nome, ?User $ator = null, string $tipo = Posto::TIPO_PRINCIPAL): Posto
    {
        $codigo = mb_strtoupper(trim($codigo));
        if (! preg_match('/^[A-Z0-9_-]{2,60}$/', $codigo) || mb_strlen(trim($nome)) < 3) {
            throw new RegraIntegracaoViolada('posto_invalido', 'Informe código (letras, números, hífen) e nome do posto.');
        }
        if (Posto::query()->where('codigo', $codigo)->exists()) {
            throw new RegraIntegracaoViolada('posto_duplicado', 'Já existe um posto com este código nesta implantação.');
        }

        $this->validarTipo($tipo);

        $posto = Posto::query()->create(['codigo' => $codigo, 'nome' => trim($nome), 'tipo' => $tipo, 'status' => 'ativo', 'created_by' => $ator?->id]);
        $this->auditar('posto_cadastrado', $posto->id, ['codigo' => $codigo, 'nome' => $posto->nome, 'tipo' => $tipo]);

        return $posto;
    }

    public function vincularPonto(Posto $posto, PontoAcesso $ponto, ?User $ator = null): PostoPontoAcesso
    {
        return DB::transaction(function () use ($posto, $ponto, $ator): PostoPontoAcesso {
            if ($posto->pontosVinculados()->where('ponto_acesso_id', $ponto->id)->whereNull('ended_at')->exists()) {
                throw new RegraIntegracaoViolada('ponto_ja_no_posto', 'Este ponto já é atendido por este posto.');
            }

            $vinculo = PostoPontoAcesso::query()->create([
                'posto_id' => $posto->id, 'ponto_acesso_id' => $ponto->id, 'started_at' => now(), 'created_by' => $ator?->id,
            ]);
            $this->auditar('posto_ponto_vinculado', $posto->id, ['ponto_acesso_id' => $ponto->id, 'ponto' => $ponto->nome]);

            return $vinculo;
        });
    }

    public function desvincularPonto(Posto $posto, PontoAcesso $ponto, ?User $ator = null): void
    {
        $encerrados = $posto->pontosVinculados()->where('ponto_acesso_id', $ponto->id)->whereNull('ended_at')->update(['ended_at' => now()]);
        if ($encerrados > 0) {
            $this->auditar('posto_ponto_desvinculado', $posto->id, ['ponto_acesso_id' => $ponto->id, 'ponto' => $ponto->nome]);
        }
    }

    /** Edita nome e tipo; o código fica, pois identifica o posto na auditoria. */
    public function editarPosto(Posto $posto, string $nome, string $tipo, ?User $ator = null): Posto
    {
        $nome = trim($nome);
        if (mb_strlen($nome) < 3) {
            throw new RegraIntegracaoViolada('posto_invalido', 'Informe o nome do posto (mínimo de 3 letras).');
        }
        $this->validarTipo($tipo);

        $antes = ['nome' => $posto->nome, 'tipo' => $posto->tipo];
        $posto->forceFill(['nome' => $nome, 'tipo' => $tipo])->save();
        $this->auditar('posto_editado', $posto->id, ['antes' => $antes, 'depois' => ['nome' => $nome, 'tipo' => $tipo]]);

        return $posto;
    }

    /** Desativa ou reativa. Posto ocupado não é desativado: o operador precisa encerrar o turno antes. */
    public function alterarStatus(Posto $posto, bool $ativo, ?User $ator = null): Posto
    {
        if (! $ativo && OperadorTurno::query()->where('posto_id', $posto->id)->whereNull('encerrado_em')->exists()) {
            throw new RegraIntegracaoViolada('posto_ocupado', 'O posto está ocupado. Ele só pode ser desativado depois que o operador encerrar o turno ou o posto for liberado.');
        }

        $posto->forceFill(['status' => $ativo ? 'ativo' : 'inativo'])->save();
        $this->auditar($ativo ? 'posto_reativado' : 'posto_desativado', $posto->id, ['codigo' => $posto->codigo, 'nome' => $posto->nome]);

        return $posto;
    }

    private function validarTipo(string $tipo): void
    {
        if (! in_array($tipo, [Posto::TIPO_PRINCIPAL, Posto::TIPO_VEICULOS, Posto::TIPO_PEDESTRES], true)) {
            throw new RegraIntegracaoViolada('posto_tipo_invalido', 'Tipo de posto inválido.');
        }
    }

    /** @param  array<string, mixed>  $metadata */
    private function auditar(string $acao, string $postoId, array $metadata): void
    {
        $this->audit->record(action: $acao, module: 'acessos', entityType: 'postos', entityId: $postoId, metadata: $metadata);
    }
}
