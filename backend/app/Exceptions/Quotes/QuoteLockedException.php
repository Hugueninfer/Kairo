<?php

namespace App\Exceptions\Quotes;

use App\Exceptions\BusinessException;

class QuoteLockedException extends BusinessException
{
    protected int $status = 422;

    public function __construct()
    {
        parent::__construct('Este orçamento já foi aceito ou recusado e não pode mais ser alterado.');
    }

    public function errorCode(): string
    {
        return 'quote_locked';
    }
}
