<?php

namespace App\Services\Quotes;

use App\DTOs\Quotes\QuoteData;
use App\Exceptions\Quotes\DiscountTooHighException;
use App\Support\Cents;

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
            $total = Cents::times($item->quantity, $item->unit_price);
            $subtotal += $total;

            $items[] = [
                'position' => $position,
                'description' => $item->description,
                'type' => $item->type,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total' => Cents::toDecimal($total),
            ];
        }

        $discount = Cents::from($data->discount);

        if ($discount > $subtotal) {
            throw new DiscountTooHighException;
        }

        $base = $subtotal - $discount;
        $tax = Cents::percent($base, $data->tax_rate);

        return [
            'items' => $items,
            'totals' => [
                'subtotal' => Cents::toDecimal($subtotal),
                'discount' => Cents::toDecimal($discount),
                'tax_rate' => $data->tax_rate,
                'tax_amount' => Cents::toDecimal($tax),
                'total' => Cents::toDecimal($base + $tax),
            ],
        ];
    }
}
