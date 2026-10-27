<?php

namespace App\DTOs\Quotes;

use App\DTOs\Data;
use App\Enums\RecurrenceFrequency;

/**
 * Cobrança recorrente de um contrato. Sem frequência, a recorrência é desligada.
 */
final readonly class RecurrenceData extends Data
{
    public function __construct(
        public ?RecurrenceFrequency $frequency,
        public ?string $amount = null,
        public ?string $starts_on = null,
        public ?string $ends_on = null,
        public int $payment_term_days = 7,
        public bool $auto_send = true,
    ) {}
}
