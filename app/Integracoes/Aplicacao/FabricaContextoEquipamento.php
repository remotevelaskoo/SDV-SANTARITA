<?php

namespace App\Integracoes\Aplicacao;

use App\Integracoes\Dominio\Dados\ContextoEquipamento;
use App\Integracoes\Infra\ResolvedorSegredo;
use App\Models\Equipamento;

/** Traduz o model para o contrato da porta, sem expor o model ao adaptador. */
class FabricaContextoEquipamento
{
    public function __construct(private ResolvedorSegredo $segredos) {}

    public function para(Equipamento $equipamento, string $correlationId): ContextoEquipamento
    {
        $credencial = $equipamento->credencialTecnicaAtiva();

        return new ContextoEquipamento(
            implantacaoId: $equipamento->implantacao_id,
            equipamentoId: $equipamento->id,
            pontoAcessoId: $equipamento->vinculoVigente?->ponto_acesso_id,
            fabricante: $equipamento->fabricante,
            modelo: $equipamento->modelo,
            firmwareVersao: $equipamento->firmware_versao,
            enderecoRede: $equipamento->endereco_rede,
            portaRede: $equipamento->porta_rede,
            protocolo: $equipamento->protocolo,
            direcao: $equipamento->direcao,
            timeoutSegundos: $equipamento->timeout_segundos,
            usuarioTecnico: $credencial?->usuario_tecnico,
            obterSegredo: fn () => $credencial === null ? null : $this->segredos->resolverCredencial($credencial),
            correlationId: $correlationId,
            esquema: $equipamento->esquema ?? 'https',
            tlsPinSha256: $equipamento->tls_pin_sha256,
        );
    }

    /**
     * Valores de segredo conhecidos, para redação de mensagens de erro.
     *
     * @return list<string>
     */
    public function segredosConhecidos(Equipamento $equipamento): array
    {
        $credencial = $equipamento->credencialTecnicaAtiva();
        $segredo = $credencial === null ? null : $this->segredos->resolverCredencial($credencial);

        return $segredo === null ? [] : [$segredo->revelar()];
    }
}
