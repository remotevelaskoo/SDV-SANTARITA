<?php

namespace App\Exceptions;

use RuntimeException;

class StalePreRegistrationException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('O pré-cadastro foi alterado por outra pessoa desde que a edição foi aberta.');
    }
}
