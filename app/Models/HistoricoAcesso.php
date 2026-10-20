<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// implantacao_id é fillable pelo mesmo motivo documentado em PreRegistration.
#[Fillable([
    'implantacao_id', 'pessoa_id', 'imovel_id', 'veiculo_id',
    'ponto_acesso', 'tipo', 'resultado', 'motivo_negacao',
    'operator_id', 'protocol', 'notes', 'occurred_at',
    'origem', 'excepcional', 'identificacao_excepcional', 'justificativa_excepcional', 'motivo',
    'posto_id', 'operador_turno_id', 'fora_do_posto', 'ponto_acesso_id', 'equipamento_id', 'operacao_integracao_id',
    'resultado_tecnico', 'correlation_id', 'chave_idempotencia',
])]
class HistoricoAcesso extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'historico_acessos';

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'excepcional' => 'boolean',
            'fora_do_posto' => 'boolean',
        ];
    }

    /** @return BelongsTo<Pessoa, $this> */
    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    /** @return BelongsTo<Imovel, $this> */
    public function imovel(): BelongsTo
    {
        return $this->belongsTo(Imovel::class);
    }

    /** @return BelongsTo<Veiculo, $this> */
    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    /** @return BelongsTo<User, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /** @return BelongsTo<Posto, $this> */
    public function posto(): BelongsTo
    {
        return $this->belongsTo(Posto::class);
    }

    /** @return BelongsTo<PontoAcesso, $this> */
    public function pontoAcessoRegistro(): BelongsTo
    {
        return $this->belongsTo(PontoAcesso::class, 'ponto_acesso_id');
    }

    /** @return BelongsTo<Equipamento, $this> */
    public function equipamento(): BelongsTo
    {
        return $this->belongsTo(Equipamento::class);
    }

    /** @return BelongsTo<OperacaoIntegracao, $this> */
    public function operacaoIntegracao(): BelongsTo
    {
        return $this->belongsTo(OperacaoIntegracao::class);
    }
}
