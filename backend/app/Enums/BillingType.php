<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum BillingType: string implements HasLabel
{
    use HasOptions;

    case Hourly = 'hourly';
    case Fixed = 'fixed';
    case NonBillable = 'non_billable';

    public function label(): string
    {
        return match ($this) {
            self::Hourly => 'Por hora',
            self::Fixed => 'Preço fechado',
            self::NonBillable => 'Não cobrável',
        };
    }
}
