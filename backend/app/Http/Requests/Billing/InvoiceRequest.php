<?php

namespace App\Http\Requests\Billing;

use App\DTOs\Billing\InvoiceData;
use App\DTOs\Billing\InvoiceItemData;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
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
            'project_id' => [
                'nullable', 'string',
                Rule::exists('projects', 'ulid')->where('company_id', $tenant->id())->whereNull('deleted_at'),
            ],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'discount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.description' => ['required', 'string', 'max:255'],
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
            'project_id' => 'projeto',
            'due_date' => 'vencimento',
            'items.*.description' => 'descrição do item',
            'items.*.quantity' => 'quantidade',
            'items.*.unit_price' => 'valor unitário',
        ];
    }

    public function toData(): InvoiceData
    {
        /** @var list<array{description: string, quantity: int|float|string, unit_price: int|float|string}> $items */
        $items = array_values($this->validated('items'));

        return new InvoiceData(
            client: $this->string('client_id')->toString(),
            due_date: $this->string('due_date')->toString(),
            items: array_map(fn (array $item) => new InvoiceItemData(
                description: trim($item['description']),
                quantity: $this->decimal($item['quantity']),
                unit_price: $this->decimal($item['unit_price']),
            ), $items),
            project: $this->filled('project_id') ? $this->string('project_id')->toString() : null,
            notes: $this->filled('notes') ? $this->string('notes')->toString() : null,
            discount: $this->decimal($this->input('discount', 0) ?? 0),
        );
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
