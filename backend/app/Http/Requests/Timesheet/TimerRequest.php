<?php

namespace App\Http\Requests\Timesheet;

use App\DTOs\Timesheet\TimerData;
use Illuminate\Foundation\Http\FormRequest;

class TimerRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'string', 'size:26'],
            'task_id' => ['nullable', 'string', 'size:26'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function toData(): TimerData
    {
        return new TimerData(
            project: $this->string('project_id')->toString(),
            task: $this->filled('task_id') ? $this->string('task_id')->toString() : null,
            description: $this->filled('description') ? $this->string('description')->trim()->toString() : null,
        );
    }
}
