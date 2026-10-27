<?php

namespace App\Exceptions\Quotes;

use App\Exceptions\BusinessException;

class ClientWithoutEmailException extends BusinessException
{
    protected int $status = 422;

    public function __construct()
    {
        parent::__construct('Cadastre o e-mail do cliente antes de enviar o orçamento.');
    }

    public function errorCode(): string
    {
        return 'client_without_email';
    }
}
