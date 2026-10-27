<?php

namespace App\Http\Requests\Billing;

use App\DTOs\Billing\PaymentData;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['amount' => 'valor', 'method' => 'forma de pagamento', 'paid_on' => 'data do pagamento'];
    }

    public function toData(): PaymentData
    {
        return new PaymentData(
            amount: number_format((float) $this->input('amount'), 2, '.', ''),
            method: $this->enum('method', PaymentMethod::class) ?? PaymentMethod::Other,
            paid_on: $this->string('paid_on')->toString(),
        );
    }
}
