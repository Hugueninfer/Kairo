<?php

namespace App\DTOs\Projects;

use App\DTOs\Data;
use App\Enums\BillingType;
use App\Enums\ProjectStatus;

/**
 * O cliente vem como ULID (é o que a API expõe); o Service troca pelo id interno.
 */
final readonly class ProjectData extends Data
{
    public function __construct(
        public string $name,
        public ?string $client = null,
        public ?string $description = null,
        public ProjectStatus $status = ProjectStatus::Active,
        public BillingType $billing_type = BillingType::Hourly,
        public ?string $hourly_rate = null,
        public ?string $budget = null,
        public ?string $starts_on = null,
        public ?string $due_on = null,
    ) {}
}
