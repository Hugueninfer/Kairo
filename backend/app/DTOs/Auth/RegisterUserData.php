<?php

namespace App\DTOs\Auth;

use App\DTOs\Data;

final readonly class RegisterUserData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}
}
