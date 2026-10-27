<?php

namespace App\Http\Requests\Billing;

use App\DTOs\Billing\ContractInvoiceData;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContractInvoiceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(TenantContext $tenant): array
    {
        return [
            'contract_id' => ['required', 'string', Rule::exists('contracts', 'ulid')->where('company_id', $tenant->id())],
            'percent' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:100'],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['contract_id' => 'contrato', 'percent' => 'percentual', 'due_date' => 'vencimento'];
    }

    public function toData(): ContractInvoiceData
    {
        return new ContractInvoiceData(
            contract: $this->string('contract_id')->toString(),
            percent: number_format((float) ($this->input('percent') ?? 100), 2, '.', ''),
            due_date: $this->string('due_date')->toString(),
            notes: $this->filled('notes') ? $this->string('notes')->toString() : null,
        );
    }
}
