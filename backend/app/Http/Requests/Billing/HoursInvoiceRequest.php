<?php

namespace App\Http\Requests\Billing;

use App\DTOs\Billing\HoursInvoiceData;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HoursInvoiceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(TenantContext $tenant): array
    {
        return [
            'project_id' => [
                'required', 'string',
                Rule::exists('projects', 'ulid')->where('company_id', $tenant->id())->whereNull('deleted_at'),
            ],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['project_id' => 'projeto', 'from' => 'início', 'to' => 'fim', 'due_date' => 'vencimento'];
    }

    public function toData(): HoursInvoiceData
    {
        return new HoursInvoiceData(
            project: $this->string('project_id')->toString(),
            from: $this->string('from')->toString(),
            to: $this->string('to')->toString(),
            due_date: $this->string('due_date')->toString(),
            notes: $this->filled('notes') ? $this->string('notes')->toString() : null,
        );
    }
}
