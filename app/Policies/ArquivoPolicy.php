<?php

namespace App\Policies;

use App\Models\Arquivo;
use App\Models\User;

class ArquivoPolicy
{
    public function view(User $user, Arquivo $arquivo): bool
    {
        return $user->hasPermission('arquivos.sensiveis.visualizar');
    }
}
