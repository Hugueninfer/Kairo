<?php

namespace App\Exceptions\Billing;

use App\Exceptions\BusinessException;

class ClientWithoutEmailException extends BusinessException
{
    protected int $status = 422;

    public function __construct()
    {
        parent::__construct('Cadastre o e-mail do cliente antes de enviar a fatura.');
    }

    public function errorCode(): string
    {
        return 'client_without_email';
    }
}
