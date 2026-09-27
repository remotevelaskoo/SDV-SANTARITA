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
        $referencia = $credencial?->referencia_segredo;

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
            obterSegredo: fn () => $referencia === null ? null : $this->segredos->resolver($referencia),
            correlationId: $correlationId,
        );
    }

    /**
     * Valores de segredo conhecidos, para redação de mensagens de erro.
     *
     * @return list<string>
     */
    public function segredosConhecidos(Equipamento $equipamento): array
    {
        $referencia = $equipamento->credencialTecnicaAtiva()?->referencia_segredo;
        $segredo = $referencia === null ? null : $this->segredos->resolver($referencia);

        return $segredo === null ? [] : [$segredo->revelar()];
    }
}
