<?php

namespace App\Exceptions\Auth;

use App\Exceptions\BusinessException;

class InvalidPasswordResetException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('O link de redefinição é inválido ou expirou. Peça um novo.');
    }

    public function errorCode(): string
    {
        return 'invalid_reset_token';
    }
}
