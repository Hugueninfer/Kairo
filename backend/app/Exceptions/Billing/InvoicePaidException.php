<?php

namespace App\Exceptions\Billing;

use App\Exceptions\BusinessException;

class InvoicePaidException extends BusinessException
{
    protected int $status = 422;

    public function __construct()
    {
        parent::__construct('Faturas com pagamento recebido não podem ser canceladas.');
    }

    public function errorCode(): string
    {
        return 'invoice_paid';
    }
}
