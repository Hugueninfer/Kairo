<?php

namespace App\DTOs\Companies;

use App\DTOs\Data;

/**
 * Régua de lembretes de cobrança da empresa.
 */
final readonly class BillingSettingsData extends Data
{
    /**
     * @param  list<string>  $reminder_stages
     */
    public function __construct(
        public bool $reminders_enabled,
        public array $reminder_stages,
        public ?string $reminder_message = null,
    ) {}
}
