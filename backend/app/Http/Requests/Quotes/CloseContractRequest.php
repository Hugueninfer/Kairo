<?php

namespace App\Http\Requests\Quotes;

use App\Enums\ContractStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CloseContractRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ContractStatus::class)->only([ContractStatus::Completed, ContractStatus::Cancelled])],
        ];
    }

    public function status(): ContractStatus
    {
        return $this->enum('status', ContractStatus::class) ?? ContractStatus::Completed;
    }
}
