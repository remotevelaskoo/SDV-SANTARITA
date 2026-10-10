<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Posto operacional da portaria (ADR-017 1.1.0, SDV-OPE-018). Define os
 * pontos e equipamentos PREFERENCIAIS do turno, não um bloqueio absoluto:
 * o posto principal alcança todos os relés autorizados; os demais mostram
 * primeiro os seus e o restante depende de permissão ampliada.
 */
#[Fillable(['implantacao_id', 'codigo', 'nome', 'tipo', 'status', 'created_by'])]
class Posto extends Model
{
    use BelongsToImplantacao, HasUuids;

    public const TIPO_PRINCIPAL = 'principal';

    public const TIPO_VEICULOS = 'veiculos';

    public const TIPO_PEDESTRES = 'pedestres';

    /** Postos iniciais da implantação Santa Rita (SDV-OPE-018 §2). */
    public const INICIAIS = [
        ['codigo' => 'GUARITA-PRINCIPAL', 'nome' => 'Guarita Principal', 'tipo' => self::TIPO_PRINCIPAL],
        ['codigo' => 'GUARITA-VEICULOS', 'nome' => 'Guarita — Atendimento de Veículos', 'tipo' => self::TIPO_VEICULOS],
        ['codigo' => 'GUARITA-PEDESTRES', 'nome' => 'Guarita — Atendimento de Pedestres', 'tipo' => self::TIPO_PEDESTRES],
    ];

    protected $table = 'postos';

    public function principal(): bool
    {
        return $this->tipo === self::TIPO_PRINCIPAL;
    }

    /** @return HasMany<PostoPontoAcesso, $this> */
    public function pontosVinculados(): HasMany
    {
        return $this->hasMany(PostoPontoAcesso::class);
    }

    /** @return BelongsToMany<PontoAcesso, $this> */
    public function pontosVigentes(): BelongsToMany
    {
        return $this->belongsToMany(PontoAcesso::class, 'posto_pontos_acesso')
            ->wherePivotNull('ended_at');
    }

    /** @return HasMany<OperadorPosto, $this> */
    public function operadores(): HasMany
    {
        return $this->hasMany(OperadorPosto::class);
    }

    public function ativo(): bool
    {
        return $this->status === 'ativo';
    }
}
