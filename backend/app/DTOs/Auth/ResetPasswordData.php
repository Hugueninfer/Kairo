<?php

namespace App\DTOs\Auth;

use App\DTOs\Data;

final readonly class ResetPasswordData extends Data
{
    public function __construct(
        public string $token,
        public string $email,
        public string $password,
    ) {}
}
