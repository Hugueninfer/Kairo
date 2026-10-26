<?php

namespace App\Exceptions\Tasks;

use App\Exceptions\BusinessException;

class TaskNotInProjectException extends BusinessException
{
    protected int $status = 422;

    public function __construct()
    {
        parent::__construct('A tarefa escolhida não é deste projeto.');
    }

    public function errorCode(): string
    {
        return 'task_not_in_project';
    }
}
