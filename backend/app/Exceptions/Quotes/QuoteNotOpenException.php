<?php

namespace App\Exceptions\Quotes;

use App\Exceptions\BusinessException;

class QuoteNotOpenException extends BusinessException
{
    protected int $status = 409;

    public function __construct()
    {
        parent::__construct('Este orçamento não está aguardando resposta.');
    }

    public function errorCode(): string
    {
        return 'quote_not_open';
    }
}
