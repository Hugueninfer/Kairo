<?php

namespace App\Exceptions\Quotes;

use App\Exceptions\BusinessException;

class QuoteExpiredException extends BusinessException
{
    protected int $status = 422;

    public function __construct()
    {
        parent::__construct('A validade deste orçamento já passou.');
    }

    public function errorCode(): string
    {
        return 'quote_expired';
    }
}
