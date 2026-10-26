<?php

namespace App\Http\Requests\Projects;

use App\DTOs\Projects\ProjectData;
use App\Enums\BillingType;
use App\Enums\ProjectStatus;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(TenantContext $tenant): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'client_id' => [
                'nullable', 'string',
                Rule::exists('clients', 'ulid')->where('company_id', $tenant->id())->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
            'billing_type' => ['sometimes', Rule::enum(BillingType::class)],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'due_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ];
    }

    public function toData(): ProjectData
    {
        return new ProjectData(
            name: $this->string('name')->trim()->toString(),
            client: $this->filled('client_id') ? $this->string('client_id')->toString() : null,
            description: $this->filled('description') ? $this->string('description')->toString() : null,
            status: $this->enum('status', ProjectStatus::class) ?? ProjectStatus::Active,
            billing_type: $this->enum('billing_type', BillingType::class) ?? BillingType::Hourly,
            hourly_rate: $this->money('hourly_rate'),
            budget: $this->money('budget'),
            starts_on: $this->filled('starts_on') ? $this->string('starts_on')->toString() : null,
            due_on: $this->filled('due_on') ? $this->string('due_on')->toString() : null,
        );
    }

    private function money(string $key): ?string
    {
        return $this->filled($key) ? number_format((float) $this->input($key), 2, '.', '') : null;
    }
}
