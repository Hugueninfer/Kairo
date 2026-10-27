<?php

namespace App\Exceptions\Billing;

use App\Exceptions\BusinessException;

class InvoiceNotPayableException extends BusinessException
{
    protected int $status = 422;

    public function __construct()
    {
        parent::__construct('Esta fatura não está aguardando pagamento.');
    }

    public function errorCode(): string
    {
        return 'invoice_not_payable';
    }
}
