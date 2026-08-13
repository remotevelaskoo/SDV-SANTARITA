<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo global (mesmo espírito de `Permissao`/`Configuracao`): a
 * definição de quais catálogos existem é fixa pelo código/seeder. Os itens
 * de cada catálogo vivem em `CatalogoItem`, por implantação.
 */
#[Fillable(['chave', 'rotulo'])]
class Catalogo extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'catalogos';

    /** @return Collection<int, CatalogoItem> */
    public function itensAtivos(): Collection
    {
        return CatalogoItem::query()
            ->where('catalogo_id', $this->id)
            ->where('status', 'ativo')
            ->orderBy('ordem')
            ->orderBy('rotulo')
            ->get();
    }

    public static function porChave(string $chave): ?self
    {
        return static::query()->where('chave', $chave)->first();
    }
}
