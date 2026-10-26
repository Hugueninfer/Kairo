<?php

namespace App\Exceptions\Projects;

use App\Exceptions\BusinessException;

class ProjectClosedException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Este projeto está concluído ou arquivado e não recebe tarefas novas.');
    }

    public function errorCode(): string
    {
        return 'project_closed';
    }
}
