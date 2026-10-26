<?php

namespace Database\Factories;

use App\Enums\BillingType;
use App\Enums\ProjectStatus;
use App\Models\Company;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ucfirst(fake()->words(3, true)),
            'status' => ProjectStatus::Active,
            'billing_type' => BillingType::Hourly,
        ];
    }

    public function status(ProjectStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
