<?php

namespace App\Exceptions\Auth;

use App\Exceptions\BusinessException;

class TwoFactorAlreadyEnabledException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('O login em duas etapas já está ligado.');
    }

    public function errorCode(): string
    {
        return 'two_factor_enabled';
    }
}
