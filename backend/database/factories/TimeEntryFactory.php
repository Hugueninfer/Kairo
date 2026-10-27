<?php

namespace Database\Factories;

use App\Enums\TimeEntryStatus;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->subDay()->setTime(9, 0);

        return [
            'project_id' => Project::factory(),
            'company_id' => fn (array $attributes) => Project::query()->withoutGlobalScopes()->findOrFail($attributes['project_id'])->company_id,
            'user_id' => User::factory(),
            'started_at' => $start,
            'ended_at' => $start->copy()->addHour(),
            'duration_minutes' => 60,
            'billable' => true,
            'hourly_rate' => '100.00',
            'status' => TimeEntryStatus::Pending,
        ];
    }

    public function running(): static
    {
        return $this->state(['started_at' => now()->subMinutes(30), 'ended_at' => null, 'duration_minutes' => 0]);
    }
}
