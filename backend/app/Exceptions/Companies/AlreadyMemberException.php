<?php

namespace App\Exceptions\Companies;

use App\Exceptions\BusinessException;

class AlreadyMemberException extends BusinessException
{
    protected int $status = 409;

    public function __construct()
    {
        parent::__construct('Esta pessoa já faz parte da empresa.');
    }

    public function errorCode(): string
    {
        return 'already_member';
    }
}
