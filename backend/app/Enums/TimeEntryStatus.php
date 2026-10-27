<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum TimeEntryStatus: string implements HasLabel
{
    use HasOptions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando aprovação',
            self::Approved => 'Aprovado',
            self::Rejected => 'Recusado',
        };
    }

    /**
     * Apontamento aprovado fica travado: vai para faturamento.
     */
    public function isLocked(): bool
    {
        return $this === self::Approved;
    }
}
