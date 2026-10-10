<?php

namespace App\Integracoes\Aplicacao;

use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\OperadorPosto;
use App\Models\PontoAcesso;
use App\Models\Posto;
use App\Models\PostoPontoAcesso;
use App\Models\User;
use App\Models\UsuarioImplantacao;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * Postos da portaria, pontos que cada um atende e operadores designados
 * (ADR-017). Nada é apagado: vínculos e designações são encerrados com data.
 */
class CadastroPostos
{
    public function __construct(private AuditService $audit) {}

    public function criarPosto(string $codigo, string $nome, ?User $ator = null): Posto
    {
        $codigo = mb_strtoupper(trim($codigo));
        if (! preg_match('/^[A-Z0-9_-]{2,60}$/', $codigo) || mb_strlen(trim($nome)) < 3) {
            throw new RegraIntegracaoViolada('posto_invalido', 'Informe código (letras, números, hífen) e nome do posto.');
        }
        if (Posto::query()->where('codigo', $codigo)->exists()) {
            throw new RegraIntegracaoViolada('posto_duplicado', 'Já existe um posto com este código nesta implantação.');
        }

        $posto = Posto::query()->create(['codigo' => $codigo, 'nome' => trim($nome), 'status' => 'ativo', 'created_by' => $ator?->id]);
        $this->auditar('posto_cadastrado', $posto->id, ['codigo' => $codigo, 'nome' => $posto->nome]);

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

    public function designarOperador(Posto $posto, User $operador, ?User $ator = null): OperadorPosto
    {
        if (! UsuarioImplantacao::query()->where('user_id', $operador->id)->where('status', 'ativa')->exists()) {
            throw new RegraIntegracaoViolada('operador_fora_da_implantacao', 'Este usuário não atua nesta implantação.');
        }
        if (OperadorPosto::query()->where('user_id', $operador->id)->where('posto_id', $posto->id)->whereNull('ended_at')->exists()) {
            throw new RegraIntegracaoViolada('operador_ja_designado', 'Este operador já está designado para o posto.');
        }

        $designacao = OperadorPosto::query()->create([
            'user_id' => $operador->id, 'posto_id' => $posto->id, 'started_at' => now(), 'created_by' => $ator?->id,
        ]);
        $this->auditar('operador_designado_ao_posto', $posto->id, ['user_id' => $operador->id, 'operador' => $operador->name]);

        return $designacao;
    }

    public function encerrarDesignacao(Posto $posto, User $operador, ?User $ator = null): void
    {
        $encerradas = OperadorPosto::query()
            ->where('user_id', $operador->id)->where('posto_id', $posto->id)->whereNull('ended_at')
            ->update(['ended_at' => now()]);
        if ($encerradas > 0) {
            $this->auditar('operador_removido_do_posto', $posto->id, ['user_id' => $operador->id, 'operador' => $operador->name]);
        }
    }

    /** @param  array<string, mixed>  $metadata */
    private function auditar(string $acao, string $postoId, array $metadata): void
    {
        $this->audit->record(action: $acao, module: 'acessos', entityType: 'postos', entityId: $postoId, metadata: $metadata);
    }
}
