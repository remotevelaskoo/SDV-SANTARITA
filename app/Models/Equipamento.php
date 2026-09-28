<?php

namespace App\Models;

use App\Integracoes\Dominio\Enums\Capacidade;
use App\Integracoes\Dominio\Enums\Direcao;
use App\Integracoes\Dominio\Enums\EstadoHomologacao;
use App\Integracoes\Dominio\Enums\EstadoSaude;
use App\Integracoes\Dominio\Enums\StatusEquipamento;
use App\Integracoes\Dominio\Enums\TipoEquipamento;
use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Equipamento físico cadastrado por implantação (ADR-007, ADR-016).
 * Nenhum segredo fica neste model: a credencial técnica fica em
 * `equipamento_credenciais`, como referência ou cifrada.
 */
#[Fillable([
    'implantacao_id', 'nome', 'tipo', 'fabricante', 'modelo', 'numero_serie',
    'endereco_rede', 'porta_rede', 'esquema', 'tls_pin_sha256', 'protocolo', 'firmware_versao', 'adaptador',
    'direcao', 'modulo_seguro_rs485', 'status', 'estado_saude', 'ultima_comunicacao_at', 'ultimo_teste_at',
    'ultimo_erro_sanitizado', 'ultima_falha_at', 'timeout_segundos', 'versao',
    'created_by', 'updated_by', 'inactivated_at',
])]
class Equipamento extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    protected $table = 'equipamentos';

    protected function casts(): array
    {
        return [
            'tipo' => TipoEquipamento::class,
            'direcao' => Direcao::class,
            'status' => StatusEquipamento::class,
            'estado_saude' => EstadoSaude::class,
            'porta_rede' => 'integer',
            'timeout_segundos' => 'integer',
            'versao' => 'integer',
            'ultima_comunicacao_at' => 'datetime',
            'ultimo_teste_at' => 'datetime',
            'ultima_falha_at' => 'datetime',
            'modulo_seguro_rs485' => 'boolean',
            'inactivated_at' => 'datetime',
        ];
    }

    /** @return HasMany<EquipamentoPontoVinculo, $this> */
    public function vinculosPonto(): HasMany
    {
        return $this->hasMany(EquipamentoPontoVinculo::class)->orderByDesc('started_at');
    }

    /** @return HasOne<EquipamentoPontoVinculo, $this> */
    public function vinculoVigente(): HasOne
    {
        return $this->hasOne(EquipamentoPontoVinculo::class)->whereNull('ended_at');
    }

    public function pontoVigente(): ?PontoAcesso
    {
        return $this->vinculoVigente?->pontoAcesso;
    }

    /** @return HasMany<EquipamentoCredencial, $this> */
    public function credenciaisTecnicas(): HasMany
    {
        return $this->hasMany(EquipamentoCredencial::class)->orderByDesc('definida_em');
    }

    public function credencialTecnicaAtiva(string $finalidade = 'administracao'): ?EquipamentoCredencial
    {
        return $this->credenciaisTecnicas()
            ->where('finalidade', $finalidade)
            ->where('status', 'ativa')
            ->first();
    }

    /** @return HasMany<EquipamentoCapacidade, $this> */
    public function capacidades(): HasMany
    {
        return $this->hasMany(EquipamentoCapacidade::class);
    }

    /**
     * Capacidade verificada e liberada para este terminal. Capacidade ainda
     * "em homologação" só vale com o terminal em homologação (bancada).
     */
    public function suporta(Capacidade $capacidade): bool
    {
        $registro = $this->capacidades()
            ->where('capacidade', $capacidade->value)
            ->where('suportada', true)
            ->first();

        return $registro !== null
            && ($registro->estado_homologacao !== EstadoHomologacao::EmHomologacao || $this->status === StatusEquipamento::EmHomologacao);
    }

    /** @return HasMany<OperacaoIntegracao, $this> */
    public function operacoes(): HasMany
    {
        return $this->hasMany(OperacaoIntegracao::class);
    }
}
