<?php

namespace App\Exceptions\Boards;

use App\Exceptions\BusinessException;

class BoardTooLargeException extends BusinessException
{
    protected int $status = 422;

    public function __construct(int $limit)
    {
        parent::__construct("Um board pode ter até {$limit} elementos. Apague alguns ou crie outro board.");
    }

    public function errorCode(): string
    {
        return 'board_too_large';
    }
}
