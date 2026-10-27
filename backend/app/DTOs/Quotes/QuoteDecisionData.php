<?php

namespace App\DTOs\Quotes;

use App\DTOs\Data;

/**
 * Resposta do cliente pelo link público. No aceite, o nome digitado e o IP ficam como registro.
 */
final readonly class QuoteDecisionData extends Data
{
    public function __construct(
        public bool $accept,
        public ?string $signer_name = null,
        public ?string $note = null,
        public ?string $ip = null,
    ) {}
}
