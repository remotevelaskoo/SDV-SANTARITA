<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nome', 'status'])]
class Organizacao extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'organizacoes';

    /** @return HasMany<Implantacao, $this> */
    public function implantacoes(): HasMany
    {
        return $this->hasMany(Implantacao::class);
    }
}
