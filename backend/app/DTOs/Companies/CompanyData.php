<?php

namespace App\DTOs\Companies;

use App\DTOs\Data;

final readonly class CompanyData extends Data
{
    public function __construct(
        public string $name,
        public ?string $document = null,
        public string $currency = 'BRL',
        public string $timezone = 'America/Sao_Paulo',
        public ?string $default_hourly_rate = null,
    ) {}
}
