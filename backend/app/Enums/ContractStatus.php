<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum ContractStatus: string implements HasLabel
{
    use HasOptions;

    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Vigente',
            self::Completed => 'Concluído',
            self::Cancelled => 'Cancelado',
        };
    }
}
