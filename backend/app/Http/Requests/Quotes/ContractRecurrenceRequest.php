<?php

namespace App\Http\Requests\Quotes;

use App\DTOs\Quotes\RecurrenceData;
use App\Enums\RecurrenceFrequency;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cobrança recorrente do contrato. Mandar frequency vazio desliga a recorrência.
 */
class ContractRecurrenceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(TenantContext $tenant): array
    {
        $today = now($tenant->company()->timezone)->toDateString();

        return [
            'frequency' => ['nullable', Rule::enum(RecurrenceFrequency::class)],
            'amount' => ['required_with:frequency', 'nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999'],
            'starts_on' => ['required_with:frequency', 'nullable', 'date_format:Y-m-d', "after_or_equal:{$today}"],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'payment_term_days' => ['sometimes', 'integer', 'min:0', 'max:90'],
            'auto_send' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'frequency' => 'frequência',
            'amount' => 'valor',
            'starts_on' => 'primeira cobrança',
            'ends_on' => 'última cobrança',
            'payment_term_days' => 'prazo de pagamento',
            'auto_send' => 'envio automático',
        ];
    }

    public function toData(): RecurrenceData
    {
        $frequency = $this->enum('frequency', RecurrenceFrequency::class);

        if ($frequency === null) {
            return new RecurrenceData(null);
        }

        return new RecurrenceData(
            frequency: $frequency,
            amount: number_format((float) $this->input('amount'), 2, '.', ''),
            starts_on: $this->string('starts_on')->toString(),
            ends_on: $this->filled('ends_on') ? $this->string('ends_on')->toString() : null,
            payment_term_days: $this->integer('payment_term_days', 7),
            auto_send: $this->boolean('auto_send', true),
        );
    }
}
