<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidTemporalRangeException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('O término da vigência não pode ser anterior ou igual ao início.');
    }
}
