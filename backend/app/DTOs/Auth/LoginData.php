<?php

namespace App\DTOs\Auth;

use App\DTOs\Data;

final readonly class LoginData extends Data
{
    public function __construct(
        public string $email,
        public string $password,
        public bool $remember = false,
    ) {}
}
