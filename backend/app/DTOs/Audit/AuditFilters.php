<?php

namespace App\DTOs\Audit;

use App\DTOs\Data;
use App\Enums\AuditSubject;

final readonly class AuditFilters extends Data
{
    public function __construct(
        public ?AuditSubject $subject = null,
        public ?int $userId = null,
        public ?string $from = null,
        public ?string $to = null,
        public int $perPage = 30,
    ) {}
}
