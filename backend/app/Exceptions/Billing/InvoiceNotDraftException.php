<?php

namespace App\Exceptions\Billing;

use App\Exceptions\BusinessException;

class InvoiceNotDraftException extends BusinessException
{
    protected int $status = 422;

    public function __construct()
    {
        parent::__construct('Só faturas em rascunho podem ser alteradas ou apagadas. Depois de enviada, cancele.');
    }

    public function errorCode(): string
    {
        return 'invoice_not_draft';
    }
}
