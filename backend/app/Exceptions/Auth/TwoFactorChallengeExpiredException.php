<?php

namespace App\Exceptions\Auth;

use App\Exceptions\BusinessException;

class TwoFactorChallengeExpiredException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('A verificação expirou. Entre de novo com e-mail e senha.');
    }

    public function errorCode(): string
    {
        return 'two_factor_expired';
    }
}
