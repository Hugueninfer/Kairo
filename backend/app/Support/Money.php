<?php

namespace App\Support;

use Illuminate\Support\Number;

/**
 * Formata valores para e-mails e PDFs no padrão brasileiro (R$ 1.234,50).
 */
class Money
{
    public static function format(string|float|null $value, string $currency = 'BRL'): string
    {
        return (string) Number::currency((float) ($value ?? 0), in: $currency, locale: 'pt_BR');
    }

    public static function quantity(string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    }
}
