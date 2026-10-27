<?php

namespace App\Support;

/**
 * Contas de dinheiro em centavos (inteiros), sem erro de arredondamento de float.
 * Os valores entram e saem como texto decimal com até 2 casas ("1500.5" → 150050 → "1500.50").
 */
final class Cents
{
    public static function from(string $value): int
    {
        $negative = str_starts_with($value, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($value, '-'), 2), 2, '');
        $cents = (int) $whole * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$cents : $cents;
    }

    public static function toDecimal(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Quantidade (2 casas) x preço unitário, arredondando meio centavo para cima.
     */
    public static function times(string $quantity, string $unitPrice): int
    {
        return intdiv(self::from($quantity) * self::from($unitPrice) + 50, 100);
    }

    /**
     * Percentual (2 casas, ex.: "6.00") de um valor em centavos, arredondado.
     */
    public static function percent(int $cents, string $rate): int
    {
        return intdiv($cents * self::from($rate) + 5000, 10000);
    }
}
