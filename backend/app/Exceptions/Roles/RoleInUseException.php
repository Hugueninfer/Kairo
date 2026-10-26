<?php

namespace App\Exceptions\Roles;

use App\Exceptions\BusinessException;

class RoleInUseException extends BusinessException
{
    protected int $status = 409;

    public function __construct()
    {
        parent::__construct('Ainda há membros com este papel. Troque o papel deles antes de apagar.');
    }

    public function errorCode(): string
    {
        return 'role_in_use';
    }
}
