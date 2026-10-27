<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum QuoteStatus: string implements HasLabel
{
    use HasOptions;

    case Draft = 'draft';
    case Sent = 'sent';
    case Viewed = 'viewed';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Sent => 'Enviado',
            self::Viewed => 'Visualizado',
            self::Accepted => 'Aceito',
            self::Rejected => 'Recusado',
            self::Expired => 'Expirado',
        };
    }

    /**
     * Aceito ou recusado é definitivo: não dá mais para editar nem reenviar.
     */
    public function isDecided(): bool
    {
        return $this === self::Accepted || $this === self::Rejected;
    }

    /**
     * O cliente só pode aceitar ou recusar o que foi enviado e ainda não venceu.
     */
    public function awaitsDecision(): bool
    {
        return $this === self::Sent || $this === self::Viewed;
    }
}
