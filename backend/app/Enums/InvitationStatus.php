<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum InvitationStatus: string implements HasLabel
{
    use HasOptions;

    case Pending = 'pending';
    case Accepted = 'accepted';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Accepted => 'Aceito',
            self::Revoked => 'Cancelado',
            self::Expired => 'Expirado',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Pending;
    }
}
