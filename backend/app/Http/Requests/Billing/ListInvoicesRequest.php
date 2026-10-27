<?php

namespace App\Http\Requests\Billing;

use App\DTOs\Billing\InvoiceFilters;
use App\Enums\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListInvoicesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(InvoiceStatus::class)],
            'client' => ['nullable', 'string', 'size:26'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function toFilters(): InvoiceFilters
    {
        return new InvoiceFilters(
            search: $this->filled('search') ? $this->string('search')->toString() : null,
            status: $this->enum('status', InvoiceStatus::class),
            client: $this->filled('client') ? $this->string('client')->toString() : null,
            perPage: $this->integer('per_page', 20),
        );
    }
}
