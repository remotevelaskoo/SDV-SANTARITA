<?php

namespace App\Services;

use App\Models\Pessoa;
use App\Models\User;
use App\Models\Veiculo;
use App\Models\VeiculoVinculo;

/**
 * Pedido do PO (13/11/2026): marca, modelo e cor informados na etapa Placa
 * vão para o cadastro da pessoa, para aparecer em "Veículos no cadastro" na
 * próxima visita. Nenhum dos três é obrigatório.
 *
 * - placa nova: cria o veículo como "pendente" (não autoriza nada) e o liga
 *   à pessoa como "autorizado";
 * - placa já cadastrada: completa ou corrige marca, modelo e cor;
 * - veículo ligado a outra pessoa: o vínculo dela não é mexido, só os campos
 *   vazios são completados.
 */
class VeiculoDoAtendimento
{
    public function __construct(private AuditService $audit) {}

    public static function doCadastro(string $placa): ?Veiculo
    {
        return $placa === '' ? null : Veiculo::query()->where('plate_normalized', Veiculo::normalizePlate($placa))->first();
    }

    public function registrar(Pessoa $pessoa, string $placa, string $marca, string $modelo, string $cor, ?User $ator): Veiculo
    {
        $placa = Veiculo::normalizePlate($placa);
        $dados = array_filter([
            'brand' => mb_substr(trim($marca), 0, 60),
            'model' => mb_substr(trim($modelo), 0, 80),
            'color' => mb_substr(trim($cor), 0, 40),
        ], fn (string $valor) => $valor !== '');

        $veiculo = Veiculo::query()->where('plate_normalized', $placa)->lockForUpdate()->first();
        $criado = $veiculo === null;
        $vinculoAtivo = $veiculo?->vinculos()->whereNull('ended_at')->first();
        $daPessoa = $vinculoAtivo === null || $vinculoAtivo->pessoa_id === $pessoa->id;

        if ($criado) {
            $veiculo = Veiculo::query()->create([
                'plate_display' => $placa,
                'plate_normalized' => $placa,
                'country' => 'BR',
                'type' => 'carro',
                'status' => 'pendente',
                ...$dados,
            ]);
        } else {
            // De outra pessoa: só completa o que está vazio.
            $mudancas = $daPessoa ? $dados : array_filter($dados, fn (string $valor, string $campo) => blank($veiculo->{$campo}), ARRAY_FILTER_USE_BOTH);
            $veiculo->fill($mudancas);
        }

        $alterado = $veiculo->getDirty();
        $veiculo->save();

        $vinculado = false;
        if ($vinculoAtivo === null) {
            VeiculoVinculo::query()->create([
                'veiculo_id' => $veiculo->id,
                'pessoa_id' => $pessoa->id,
                'tipo' => 'autorizado',
                'status' => 'ativo',
                'started_at' => now(),
                'versao' => 1,
            ]);
            $vinculado = true;
        }

        if ($criado || $vinculado || $alterado !== []) {
            $this->audit->record(
                action: $criado ? 'veiculo_cadastrado_no_atendimento' : 'veiculo_atualizado_no_atendimento',
                module: 'validacao',
                entityType: 'veiculos',
                entityId: $veiculo->id,
                metadata: ['pessoa_id' => $pessoa->id, 'campos' => array_keys($criado ? $dados : $alterado), 'vinculado' => $vinculado],
            );
        }

        return $veiculo;
    }
}
