<?php

namespace App\Http\Requests\Timesheet;

use App\Enums\TimeEntryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewTimeEntriesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['string', 'size:26'],
            'status' => ['required', Rule::in([TimeEntryStatus::Approved->value, TimeEntryStatus::Rejected->value])],
        ];
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        /** @var list<string> */
        return array_values($this->array('ids'));
    }

    public function status(): TimeEntryStatus
    {
        /** @var TimeEntryStatus */
        return $this->enum('status', TimeEntryStatus::class);
    }
}
