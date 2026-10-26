<?php

namespace App\Http\Requests\Companies;

use App\DTOs\Companies\CompanyData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Usado para criar e editar a empresa. A permissão de editar é checada na rota.
 */
class CompanyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'document' => ['nullable', 'string', 'max:20'],
            'currency' => ['sometimes', 'string', Rule::in(['BRL', 'USD', 'EUR'])],
            'timezone' => ['sometimes', 'string', 'timezone:all'],
            'default_hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    public function toData(): CompanyData
    {
        $rate = $this->input('default_hourly_rate');

        return new CompanyData(
            name: $this->string('name')->trim()->toString(),
            document: $this->filled('document') ? $this->string('document')->trim()->toString() : null,
            currency: $this->string('currency', 'BRL')->toString(),
            timezone: $this->string('timezone', 'America/Sao_Paulo')->toString(),
            default_hourly_rate: $rate === null || $rate === '' ? null : number_format((float) $rate, 2, '.', ''),
        );
    }
}
