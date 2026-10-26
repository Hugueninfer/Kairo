<?php

namespace App\Exceptions\Tasks;

use App\Exceptions\BusinessException;

class AssigneeNotMemberException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('A tarefa só pode ser atribuída a alguém da empresa.');
    }

    public function errorCode(): string
    {
        return 'assignee_not_member';
    }
}
