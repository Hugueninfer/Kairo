<?php

namespace App\Exceptions\Companies;

use App\Exceptions\BusinessException;

class InvitationEmailMismatchException extends BusinessException
{
    protected int $status = 403;

    public function __construct()
    {
        parent::__construct('Este convite foi enviado para outro e-mail. Entre com a conta convidada.');
    }

    public function errorCode(): string
    {
        return 'invitation_email_mismatch';
    }
}
