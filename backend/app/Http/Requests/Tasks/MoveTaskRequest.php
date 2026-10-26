<?php

namespace App\Http\Requests\Tasks;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveTaskRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'position' => ['required', 'integer', 'min:0'],
        ];
    }

    public function status(): TaskStatus
    {
        /** @var TaskStatus */
        return $this->enum('status', TaskStatus::class);
    }

    public function position(): int
    {
        return $this->integer('position');
    }
}
