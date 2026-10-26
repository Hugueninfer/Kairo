<?php

namespace App\Exceptions\Timesheet;

use App\Exceptions\BusinessException;

class TimerNotRunningException extends BusinessException
{
    protected int $status = 404;

    public function __construct()
    {
        parent::__construct('Não há timer rodando.');
    }

    public function errorCode(): string
    {
        return 'timer_not_running';
    }
}
