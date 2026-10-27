<?php

namespace App\Integrations\Payments\Exceptions;

use App\Exceptions\BusinessException;

class InvalidWebhookException extends BusinessException
{
    protected int $status = 403;

    public function __construct()
    {
        parent::__construct('Aviso de pagamento com assinatura inválida.');
    }

    public function errorCode(): string
    {
        return 'invalid_webhook';
    }
}
