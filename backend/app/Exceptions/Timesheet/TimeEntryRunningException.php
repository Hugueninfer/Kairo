<?php

namespace App\Exceptions\Timesheet;

use App\Exceptions\BusinessException;

class TimeEntryRunningException extends BusinessException
{
    protected int $status = 422;

    public function __construct()
    {
        parent::__construct('Pare o timer antes de alterar este apontamento.');
    }

    public function errorCode(): string
    {
        return 'time_entry_running';
    }
}
