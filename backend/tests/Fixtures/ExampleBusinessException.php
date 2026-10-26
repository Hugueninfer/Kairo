<?php

namespace Tests\Fixtures;

use App\Exceptions\BusinessException;

class ExampleBusinessException extends BusinessException
{
    protected int $status = 409;

    public function errorCode(): string
    {
        return 'example_conflict';
    }
}
