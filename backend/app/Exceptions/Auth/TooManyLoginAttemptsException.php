<?php

namespace App\Exceptions\Auth;

use App\Exceptions\BusinessException;

class TooManyLoginAttemptsException extends BusinessException
{
    protected int $status = 429;

    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct("Muitas tentativas de login. Tente novamente em {$retryAfterSeconds} segundos.");
    }

    public function errorCode(): string
    {
        return 'too_many_attempts';
    }
}
