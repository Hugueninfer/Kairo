<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'company_id' => fn (array $attributes) => Project::query()->withoutGlobalScopes()->findOrFail($attributes['project_id'])->company_id,
            'title' => fake()->sentence(4),
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::Medium,
            'position' => 0,
        ];
    }
}
