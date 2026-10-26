<?php

namespace App\Enums\Contracts;

interface HasLabel
{
    /**
     * Texto em português exibido no front.
     */
    public function label(): string;
}
