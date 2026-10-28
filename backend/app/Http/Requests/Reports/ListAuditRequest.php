<?php

namespace App\Http\Requests\Reports;

use App\DTOs\Audit\AuditFilters;
use App\Enums\AuditSubject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAuditRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['nullable', Rule::enum(AuditSubject::class)],
            'user' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['subject' => 'tipo', 'user' => 'pessoa', 'from' => 'início', 'to' => 'fim'];
    }

    public function toFilters(): AuditFilters
    {
        return new AuditFilters(
            subject: $this->filled('subject') ? AuditSubject::from($this->string('subject')->toString()) : null,
            userId: $this->filled('user') ? $this->integer('user') : null,
            from: $this->filled('from') ? $this->string('from')->toString() : null,
            to: $this->filled('to') ? $this->string('to')->toString() : null,
            perPage: $this->integer('per_page', 30),
        );
    }
}
