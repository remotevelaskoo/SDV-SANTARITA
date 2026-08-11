<?php

namespace App\Policies;

use App\Models\PreRegistration;
use App\Models\User;

class PreRegistrationPolicy
{
    /**
     * Permissão para corrigir os dados textuais de um pré-cadastro antes da aprovação.
     * O estado do pré-cadastro (aguardando análise) é verificado separadamente do operador,
     * para que a mensagem de erro distinga "sem permissão" de "não pode mais ser editado".
     */
    public function edit(User $user, PreRegistration $preRegistration): bool
    {
        return $user->can_edit_pre_registrations;
    }
}
