<?php

namespace App\Exceptions\Auth;

use App\Exceptions\BusinessException;

class InvalidTwoFactorCodeException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Código inválido. Confira o aplicativo autenticador e tente de novo.');
    }

    public function errorCode(): string
    {
        return 'invalid_two_factor_code';
    }
}
