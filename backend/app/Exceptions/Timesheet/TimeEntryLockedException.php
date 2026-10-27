<?php

namespace App\Exceptions\Timesheet;

use App\Exceptions\BusinessException;

class TimeEntryLockedException extends BusinessException
{
    protected int $status = 422;

    public function __construct()
    {
        parent::__construct('Este apontamento já foi aprovado e não pode mais ser alterado.');
    }

    public function errorCode(): string
    {
        return 'time_entry_locked';
    }
}
