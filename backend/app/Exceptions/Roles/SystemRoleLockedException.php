<?php

namespace App\Exceptions\Roles;

use App\Exceptions\BusinessException;

class SystemRoleLockedException extends BusinessException
{
    protected int $status = 422;

    public function __construct()
    {
        parent::__construct('Papéis padrão não podem ser apagados.');
    }

    public function errorCode(): string
    {
        return 'system_role_locked';
    }
}
