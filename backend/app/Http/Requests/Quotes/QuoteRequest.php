<?php

namespace App\Http\Requests\Quotes;

use App\DTOs\Quotes\QuoteData;
use App\DTOs\Quotes\QuoteItemData;
use App\Enums\QuoteItemType;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(TenantContext $tenant): array
    {
        return [
            'client_id' => [
                'required', 'string',
                Rule::exists('clients', 'ulid')->where('company_id', $tenant->id())->whereNull('deleted_at'),
            ],
            'title' => ['required', 'string', 'max:160'],
            'introduction' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:10000'],
            'valid_until' => ['required', 'date_format:Y-m-d'],
            'discount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'tax_rate' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.type' => ['required', Rule::enum(QuoteItemType::class)],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999'],
            'items.*.unit_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'items.*.description' => 'descrição do item',
            'items.*.quantity' => 'quantidade',
            'items.*.unit_price' => 'valor unitário',
        ];
    }

    public function toData(): QuoteData
    {
        /** @var list<array{description: string, type: string, quantity: int|float|string, unit_price: int|float|string}> $items */
        $items = array_values($this->validated('items'));

        return new QuoteData(
            client: $this->string('client_id')->toString(),
            title: $this->string('title')->trim()->toString(),
            valid_until: $this->string('valid_until')->toString(),
            items: array_map(fn (array $item) => new QuoteItemData(
                description: trim($item['description']),
                type: QuoteItemType::from($item['type']),
                quantity: $this->decimal($item['quantity']),
                unit_price: $this->decimal($item['unit_price']),
            ), $items),
            introduction: $this->filled('introduction') ? $this->string('introduction')->toString() : null,
            terms: $this->filled('terms') ? $this->string('terms')->toString() : null,
            discount: $this->decimal($this->input('discount', 0) ?? 0),
            tax_rate: $this->decimal($this->input('tax_rate', 0) ?? 0),
        );
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
