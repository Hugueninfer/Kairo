<?php

namespace App\Exceptions\Auth;

use App\Exceptions\BusinessException;

class InvalidVerificationLinkException extends BusinessException
{
    protected int $status = 403;

    public function __construct()
    {
        parent::__construct('O link de verificação é inválido.');
    }

    public function errorCode(): string
    {
        return 'invalid_verification_link';
    }
}
