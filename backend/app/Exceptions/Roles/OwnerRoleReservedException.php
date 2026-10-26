<?php

namespace App\Exceptions\Roles;

use App\Exceptions\BusinessException;

class OwnerRoleReservedException extends BusinessException
{
    protected int $status = 422;

    public function __construct()
    {
        parent::__construct('O papel de dono é exclusivo do dono da empresa.');
    }

    public function errorCode(): string
    {
        return 'owner_role_reserved';
    }
}
