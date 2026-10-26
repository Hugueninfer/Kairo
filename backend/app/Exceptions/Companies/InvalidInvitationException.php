<?php

namespace App\Exceptions\Companies;

use App\Exceptions\BusinessException;

class InvalidInvitationException extends BusinessException
{
    protected int $status = 404;

    public function __construct()
    {
        parent::__construct('Este convite não existe, expirou ou já foi usado.');
    }

    public function errorCode(): string
    {
        return 'invalid_invitation';
    }
}
