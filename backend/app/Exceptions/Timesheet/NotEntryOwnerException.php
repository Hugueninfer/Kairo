<?php

namespace App\Exceptions\Timesheet;

use App\Exceptions\BusinessException;

class NotEntryOwnerException extends BusinessException
{
    protected int $status = 403;

    public function __construct()
    {
        parent::__construct('Só quem lançou as horas pode alterar este apontamento.');
    }

    public function errorCode(): string
    {
        return 'not_entry_owner';
    }
}
