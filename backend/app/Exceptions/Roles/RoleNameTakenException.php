<?php

namespace App\Exceptions\Roles;

use App\Exceptions\BusinessException;

class RoleNameTakenException extends BusinessException
{
    protected int $status = 422;

    public function __construct()
    {
        parent::__construct('Já existe um papel com este nome.');
    }

    public function errorCode(): string
    {
        return 'role_name_taken';
    }
}
