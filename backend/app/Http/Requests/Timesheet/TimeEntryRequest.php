<?php

namespace App\Http\Requests\Timesheet;

use App\DTOs\Timesheet\TimeEntryData;
use Illuminate\Foundation\Http\FormRequest;

class TimeEntryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'string', 'size:26'],
            'task_id' => ['nullable', 'string', 'size:26'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:+1 day'],
            'start' => ['nullable', 'date_format:H:i'],
            // Até 24h num lançamento só.
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'description' => ['nullable', 'string', 'max:500'],
            'billable' => ['nullable', 'boolean'],
        ];
    }

    public function toData(): TimeEntryData
    {
        return new TimeEntryData(
            project: $this->string('project_id')->toString(),
            date: $this->string('date')->toString(),
            minutes: $this->integer('minutes'),
            task: $this->filled('task_id') ? $this->string('task_id')->toString() : null,
            start: $this->filled('start') ? $this->string('start')->toString() : null,
            description: $this->filled('description') ? $this->string('description')->trim()->toString() : null,
            billable: $this->has('billable') && $this->input('billable') !== null ? $this->boolean('billable') : null,
        );
    }
}
