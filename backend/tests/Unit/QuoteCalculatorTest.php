<?php

use App\DTOs\Quotes\QuoteData;
use App\DTOs\Quotes\QuoteItemData;
use App\Enums\QuoteItemType;
use App\Exceptions\Quotes\DiscountTooHighException;
use App\Services\Quotes\QuoteCalculator;

function quoteWith(array $items, string $discount = '0.00', string $taxRate = '0.00'): QuoteData
{
    return new QuoteData(
        client: 'x',
        title: 'Site',
        valid_until: '2026-12-01',
        items: array_map(fn (array $item) => new QuoteItemData('Item', $item[0], $item[1], $item[2]), $items),
        discount: $discount,
        tax_rate: $taxRate,
    );
}

it('soma itens em centavos sem erro de arredondamento', function () {
    $result = (new QuoteCalculator)->calculate(quoteWith([
        [QuoteItemType::Hourly, '12.50', '150.00'],
        [QuoteItemType::Fixed, '3.00', '0.10'],
        [QuoteItemType::Fixed, '1.00', '0.20'],
    ]));

    expect(array_column($result['items'], 'total'))->toBe(['1875.00', '0.30', '0.20'])
        ->and($result['totals']['subtotal'])->toBe('1875.50')
        ->and($result['totals']['total'])->toBe('1875.50');
});

it('aplica desconto e depois o imposto sobre o valor com desconto', function () {
    $result = (new QuoteCalculator)->calculate(quoteWith([[QuoteItemType::Fixed, '1.00', '1000.00']], '100.00', '6.00'));

    expect($result['totals'])->toMatchArray([
        'subtotal' => '1000.00',
        'discount' => '100.00',
        'tax_amount' => '54.00',
        'total' => '954.00',
    ]);
});

it('arredonda meio centavo para cima', function () {
    $result = (new QuoteCalculator)->calculate(quoteWith([[QuoteItemType::Hourly, '0.50', '0.05']], '0.00', '15.00'));

    // 0,5 x 0,05 = 0,025 → 0,03; imposto 15% de 0,03 = 0,0045 → 0,00
    expect($result['items'][0]['total'])->toBe('0.03')
        ->and($result['totals']['total'])->toBe('0.03');
});

it('recusa desconto maior que o subtotal', function () {
    (new QuoteCalculator)->calculate(quoteWith([[QuoteItemType::Fixed, '1.00', '10.00']], '10.01'));
})->throws(DiscountTooHighException::class);
