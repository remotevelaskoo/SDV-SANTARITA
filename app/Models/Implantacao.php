<?php

namespace App\Models;

use App\Support\ImplantacaoContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organizacao_id', 'nome', 'slug', 'status'])]
class Implantacao extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'implantacoes';

    /** @return BelongsTo<Organizacao, $this> */
    public function organizacao(): BelongsTo
    {
        return $this->belongsTo(Organizacao::class);
    }

    public static function current(): self
    {
        return ImplantacaoContext::current();
    }
}
