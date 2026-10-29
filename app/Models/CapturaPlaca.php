<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Imagem estática da câmera de placas guardada como evidência (AJ-006).
 * Depois de ligada a uma decisão de acesso, a captura não muda mais.
 */
#[Fillable([
    'implantacao_id', 'equipamento_id', 'ponto_acesso_id', 'posto_id', 'arquivo_id', 'operador_id',
    'capturada_em', 'origem', 'placa_lida', 'confianca_leitura', 'leitor',
    'historico_acesso_id', 'vinculada_em',
])]
class CapturaPlaca extends Model
{
    use BelongsToImplantacao, HasUuids;

    protected $table = 'capturas_placa';

    protected static function booted(): void
    {
        static::updating(function (CapturaPlaca $captura): void {
            if ($captura->getOriginal('historico_acesso_id') !== null) {
                throw new LogicException('Evidência de placa já ligada a uma decisão não pode ser alterada.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Evidência de placa não pode ser apagada.');
        });
    }

    protected function casts(): array
    {
        return [
            'capturada_em' => 'datetime',
            'vinculada_em' => 'datetime',
            'confianca_leitura' => 'float',
        ];
    }

    public function simulada(): bool
    {
        return $this->origem === 'simulador';
    }

    /** @return BelongsTo<Equipamento, $this> */
    public function equipamento(): BelongsTo
    {
        return $this->belongsTo(Equipamento::class);
    }

    /** @return BelongsTo<PontoAcesso, $this> */
    public function pontoAcesso(): BelongsTo
    {
        return $this->belongsTo(PontoAcesso::class);
    }

    /** @return BelongsTo<Arquivo, $this> */
    public function arquivo(): BelongsTo
    {
        return $this->belongsTo(Arquivo::class);
    }

    /** @return BelongsTo<HistoricoAcesso, $this> */
    public function historicoAcesso(): BelongsTo
    {
        return $this->belongsTo(HistoricoAcesso::class);
    }
}
