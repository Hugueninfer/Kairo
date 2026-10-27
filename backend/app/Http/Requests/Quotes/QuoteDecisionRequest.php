<?php

namespace App\Http\Requests\Quotes;

use App\DTOs\Quotes\QuoteDecisionData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Resposta do cliente pelo link público. Aceitar exige digitar o nome, como assinatura.
 */
class QuoteDecisionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:accept,reject'],
            'signer_name' => ['required_if:decision,accept', 'nullable', 'string', 'min:3', 'max:160'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['signer_name' => 'nome completo'];
    }

    public function toData(): QuoteDecisionData
    {
        $accept = $this->input('decision') === 'accept';

        return new QuoteDecisionData(
            accept: $accept,
            signer_name: $accept ? $this->string('signer_name')->trim()->toString() : null,
            note: $this->filled('note') ? $this->string('note')->trim()->toString() : null,
            ip: $this->ip(),
        );
    }
}
