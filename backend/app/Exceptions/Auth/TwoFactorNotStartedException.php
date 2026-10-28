<?php

namespace App\Exceptions\Auth;

use App\Exceptions\BusinessException;

class TwoFactorNotStartedException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Comece a configuração do login em duas etapas antes de confirmar.');
    }

    public function errorCode(): string
    {
        return 'two_factor_not_started';
    }
}
