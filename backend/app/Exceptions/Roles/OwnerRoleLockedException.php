<?php

namespace App\Exceptions\Roles;

use App\Exceptions\BusinessException;

class OwnerRoleLockedException extends BusinessException
{
    protected int $status = 422;

    public function __construct()
    {
        parent::__construct('O papel de dono sempre tem todas as permissões e não pode ser alterado.');
    }

    public function errorCode(): string
    {
        return 'owner_role_locked';
    }
}
