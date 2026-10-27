<?php

namespace App\Http\Requests\Companies;

use App\DTOs\Companies\BillingSettingsData;
use App\Enums\ReminderStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BillingSettingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reminders_enabled' => ['required', 'boolean'],
            'reminder_stages' => ['present', 'array'],
            'reminder_stages.*' => ['distinct', Rule::enum(ReminderStage::class)],
            'reminder_message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reminders_enabled' => 'lembretes',
            'reminder_stages' => 'etapas',
            'reminder_stages.*' => 'etapa',
            'reminder_message' => 'recado',
        ];
    }

    public function toData(): BillingSettingsData
    {
        /** @var list<string> $stages */
        $stages = array_values(array_unique(array_map('strval', (array) $this->input('reminder_stages', []))));

        return new BillingSettingsData(
            reminders_enabled: $this->boolean('reminders_enabled'),
            reminder_stages: $stages,
            reminder_message: $this->filled('reminder_message') ? $this->string('reminder_message')->trim()->toString() : null,
        );
    }
}
