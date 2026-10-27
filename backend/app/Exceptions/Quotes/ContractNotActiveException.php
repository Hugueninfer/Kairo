<?php

namespace App\Exceptions\Quotes;

use App\Exceptions\BusinessException;

class ContractNotActiveException extends BusinessException
{
    protected int $status = 422;

    public function __construct()
    {
        parent::__construct('Só contratos vigentes podem ser alterados, concluídos ou cancelados.');
    }

    public function errorCode(): string
    {
        return 'contract_not_active';
    }
}
