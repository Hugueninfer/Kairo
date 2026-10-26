<?php

namespace App\Http\Requests\Tasks;

use App\DTOs\Tasks\TaskData;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Quem pode receber a tarefa (membro da empresa) é conferido no TaskService.
 */
class TaskRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'assignee_id' => ['nullable', 'integer'],
            'estimate_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function toData(): TaskData
    {
        return new TaskData(
            title: $this->string('title')->trim()->toString(),
            description: $this->filled('description') ? $this->string('description')->toString() : null,
            status: $this->enum('status', TaskStatus::class) ?? TaskStatus::Todo,
            priority: $this->enum('priority', TaskPriority::class) ?? TaskPriority::Medium,
            assignee_id: $this->filled('assignee_id') ? $this->integer('assignee_id') : null,
            estimate_minutes: $this->filled('estimate_minutes') ? $this->integer('estimate_minutes') : null,
            due_on: $this->filled('due_on') ? $this->string('due_on')->toString() : null,
        );
    }
}
