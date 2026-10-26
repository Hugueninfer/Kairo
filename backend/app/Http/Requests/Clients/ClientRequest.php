<?php

namespace App\Http\Requests\Clients;

use App\DTOs\Clients\ClientData;
use Illuminate\Foundation\Http\FormRequest;

class ClientRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'document' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function toData(): ClientData
    {
        return new ClientData(
            name: $this->string('name')->trim()->toString(),
            email: $this->optional('email'),
            phone: $this->optional('phone'),
            document: $this->optional('document'),
            notes: $this->optional('notes'),
        );
    }

    private function optional(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->trim()->toString() : null;
    }
}
