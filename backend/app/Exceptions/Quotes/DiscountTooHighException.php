<?php

namespace App\Exceptions\Quotes;

use App\Exceptions\BusinessException;

class DiscountTooHighException extends BusinessException
{
    protected int $status = 422;

    public function __construct()
    {
        parent::__construct('O desconto não pode ser maior que a soma dos itens.');
    }

    public function errorCode(): string
    {
        return 'discount_too_high';
    }
}
