<?php

namespace App\Integrations\Payments\Exceptions;

use App\Exceptions\BusinessException;

/**
 * O gateway recusou ou não respondeu. A mensagem original fica no log, não vai para o usuário.
 */
class GatewayException extends BusinessException
{
    protected int $status = 502;

    public function __construct(public readonly string $detail = '')
    {
        parent::__construct('O gateway de pagamento não respondeu. Tente de novo em alguns minutos.');
    }

    public function errorCode(): string
    {
        return 'gateway_error';
    }
}
