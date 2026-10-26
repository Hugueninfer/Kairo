<?php

namespace App\Exceptions\Companies;

use App\Exceptions\BusinessException;

class NoActiveCompanyException extends BusinessException
{
    protected int $status = 409;

    public function __construct()
    {
        parent::__construct('Selecione ou crie uma empresa para continuar.');
    }

    public function errorCode(): string
    {
        return 'no_active_company';
    }
}
