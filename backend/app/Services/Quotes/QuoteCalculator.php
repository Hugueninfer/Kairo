<?php

namespace App\Services\Quotes;

use App\DTOs\Quotes\QuoteData;
use App\Exceptions\Quotes\DiscountTooHighException;

/**
 * Calcula itens e totais do orçamento em centavos (inteiros), sem erro de arredondamento de float.
 *
 * total = (subtotal - desconto) + imposto, com o imposto em % sobre o valor já com desconto.
 */
class QuoteCalculator
{
    /**
     * @return array{items: list<array<string, mixed>>, totals: array<string, string>}
     */
    public function calculate(QuoteData $data): array
    {
        $items = [];
        $subtotal = 0;

        foreach ($data->items as $position => $item) {
            // quantidade e preço com 2 casas: centavos x centésimos / 100.
            $total = intdiv($this->cents($item->quantity) * $this->cents($item->unit_price) + 50, 100);
            $subtotal += $total;

            $items[] = [
                'position' => $position,
                'description' => $item->description,
                'type' => $item->type,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total' => $this->decimal($total),
            ];
        }

        $discount = $this->cents($data->discount);

        if ($discount > $subtotal) {
            throw new DiscountTooHighException;
        }

        $base = $subtotal - $discount;
        $tax = intdiv($base * $this->cents($data->tax_rate) + 5000, 10000);

        return [
            'items' => $items,
            'totals' => [
                'subtotal' => $this->decimal($subtotal),
                'discount' => $this->decimal($discount),
                'tax_rate' => $data->tax_rate,
                'tax_amount' => $this->decimal($tax),
                'total' => $this->decimal($base + $tax),
            ],
        ];
    }

    /**
     * "12.5" → 1250. A entrada já vem validada como número com até 2 casas.
     */
    private function cents(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
