<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo global de configurações (ADR-002 seção 8.1, mesmo espírito de
 * `Permissao`): o conjunto de chaves configuráveis é definido pelo
 * código/seeder, não por implantação. Cada implantação pode sobrescrever o
 * valor via `ImplantacaoConfiguracao` — ausência de override usa
 * `valor_padrao`.
 */
#[Fillable(['chave', 'categoria', 'tipo', 'rotulo', 'descricao', 'valor_padrao'])]
class Configuracao extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'configuracoes';

    public function valorEfetivo(): ?string
    {
        return ImplantacaoConfiguracao::query()->where('configuracao_id', $this->id)->value('valor')
            ?? $this->valor_padrao;
    }

    public static function obter(string $chave): ?string
    {
        return static::query()->where('chave', $chave)->first()?->valorEfetivo();
    }
}
