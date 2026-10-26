<?php

namespace App\Exceptions\Companies;

use App\Exceptions\BusinessException;

class CannotRemoveOwnerException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('O dono da empresa não pode ser removido.');
    }

    public function errorCode(): string
    {
        return 'cannot_remove_owner';
    }
}
