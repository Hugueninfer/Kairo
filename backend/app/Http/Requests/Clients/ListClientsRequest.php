<?php

namespace App\Http\Requests\Clients;

use App\DTOs\Clients\ClientFilters;
use Illuminate\Foundation\Http\FormRequest;

class ListClientsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function toFilters(): ClientFilters
    {
        return new ClientFilters(
            search: $this->filled('search') ? $this->string('search')->toString() : null,
            perPage: $this->integer('per_page', 20),
        );
    }
}
