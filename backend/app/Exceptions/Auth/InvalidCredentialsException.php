<?php

namespace App\Exceptions\Auth;

use App\Exceptions\BusinessException;

class InvalidCredentialsException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('E-mail ou senha inválidos.');
    }

    public function errorCode(): string
    {
        return 'invalid_credentials';
    }
}
