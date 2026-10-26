<?php

namespace App\Exceptions\Companies;

use App\Exceptions\BusinessException;

class NotCompanyMemberException extends BusinessException
{
    protected int $status = 403;

    public function __construct()
    {
        parent::__construct('Você não faz parte desta empresa.');
    }

    public function errorCode(): string
    {
        return 'not_company_member';
    }
}
