<?php

namespace App\DTOs\Billing;

use App\DTOs\Data;

/**
 * Faturar um contrato, inteiro ou uma parcela (ex.: 50% na entrada).
 */
final readonly class ContractInvoiceData extends Data
{
    public function __construct(
        public string $contract,
        public string $percent,
        public string $due_date,
        public ?string $notes = null,
    ) {}
}
