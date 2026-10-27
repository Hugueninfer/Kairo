<?php

namespace App\Exceptions\Billing;

use App\Exceptions\BusinessException;

class PaymentExceedsBalanceException extends BusinessException
{
    protected int $status = 422;

    public function __construct()
    {
        parent::__construct('O valor é maior que o saldo em aberto da fatura.');
    }

    public function errorCode(): string
    {
        return 'payment_exceeds_balance';
    }
}
