<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum QuoteItemType: string implements HasLabel
{
    use HasOptions;

    case Fixed = 'fixed';
    case Hourly = 'hourly';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Valor fixo',
            self::Hourly => 'Por hora',
        };
    }

    /**
     * Unidade mostrada ao lado da quantidade no orçamento e no PDF.
     */
    public function unit(): string
    {
        return match ($this) {
            self::Fixed => 'un.',
            self::Hourly => 'h',
        };
    }
}
