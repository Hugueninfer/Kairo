<?php

namespace App\Http\Requests\Quotes;

use App\DTOs\Quotes\QuoteFilters;
use App\Enums\QuoteStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListQuotesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(QuoteStatus::class)],
            'client' => ['nullable', 'string', 'size:26'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function toFilters(): QuoteFilters
    {
        return new QuoteFilters(
            search: $this->filled('search') ? $this->string('search')->toString() : null,
            status: $this->enum('status', QuoteStatus::class),
            client: $this->filled('client') ? $this->string('client')->toString() : null,
            perPage: $this->integer('per_page', 20),
        );
    }
}
