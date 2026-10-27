<?php

namespace App\DTOs\Billing;

use App\DTOs\Data;

/**
 * Faturar as horas aprovadas de um projeto num período (datas no fuso da empresa).
 */
final readonly class HoursInvoiceData extends Data
{
    public function __construct(
        public string $project,
        public string $from,
        public string $to,
        public string $due_date,
        public ?string $notes = null,
    ) {}
}
