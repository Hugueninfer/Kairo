<?php

namespace App\DTOs\Clients;

use App\DTOs\Data;

final readonly class ClientData extends Data
{
    public function __construct(
        public string $name,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $document = null,
        public ?string $notes = null,
    ) {}
}
