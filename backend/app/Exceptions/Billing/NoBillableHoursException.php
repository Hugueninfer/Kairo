<?php

namespace App\Exceptions\Billing;

use App\Exceptions\BusinessException;

class NoBillableHoursException extends BusinessException
{
    protected int $status = 422;

    public function __construct()
    {
        parent::__construct('Não há horas aprovadas e ainda não faturadas neste projeto no período.');
    }

    public function errorCode(): string
    {
        return 'no_billable_hours';
    }
}
