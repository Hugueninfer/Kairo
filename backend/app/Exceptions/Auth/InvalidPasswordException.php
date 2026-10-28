<?php

namespace App\Exceptions\Auth;

use App\Exceptions\BusinessException;

class InvalidPasswordException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Senha incorreta.');
    }

    public function errorCode(): string
    {
        return 'invalid_password';
    }
}
