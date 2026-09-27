<?php

namespace App\Models;

use App\Integracoes\Dominio\Enums\EstadoOutbox;
use App\Integracoes\Dominio\Enums\OperacaoIntegracao as TipoOperacao;
use App\Integracoes\Dominio\Enums\ResultadoEquipamento;
use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Mensagem da outbox de integração com registro de idempotência (ADR-004, ADR-005). */
#[Fillable([
    'implantacao_id', 'equipamento_id', 'operacao', 'fila', 'chave_idempotencia',
    'hash_payload', 'versao_contrato', 'payload', 'agregado_tipo', 'agregado_id',
    'correlation_id', 'causation_id', 'estado', 'resultado', 'tentativas',
    'max_tentativas', 'disponivel_em', 'lease_ate', 'lease_token', 'expira_em',
    'processado_em', 'erro_sanitizado', 'resultado_dados', 'origem', 'ator_id',
])]
class OperacaoIntegracao extends Model
{
    use BelongsToImplantacao, HasUuids;

    protected $table = 'operacoes_integracao';

    protected function casts(): array
    {
        return [
            'operacao' => TipoOperacao::class,
            'estado' => EstadoOutbox::class,
            'resultado' => ResultadoEquipamento::class,
            'payload' => 'array',
            'resultado_dados' => 'array',
            'tentativas' => 'integer',
            'max_tentativas' => 'integer',
            'disponivel_em' => 'datetime',
            'lease_ate' => 'datetime',
            'expira_em' => 'datetime',
            'processado_em' => 'datetime',
        ];
    }

    /** @return BelongsTo<Equipamento, $this> */
    public function equipamento(): BelongsTo
    {
        return $this->belongsTo(Equipamento::class);
    }

    /** @return HasMany<IntegracaoOcorrencia, $this> */
    public function ocorrencias(): HasMany
    {
        return $this->hasMany(IntegracaoOcorrencia::class);
    }
}
