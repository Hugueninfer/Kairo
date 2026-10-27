<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Aviso recebido de um gateway de pagamento. O par (gateway, event_id) é único,
 * então o mesmo aviso reenviado não é processado duas vezes.
 *
 * @property array<string, mixed> $payload
 * @property Carbon|null $processed_at
 */
#[Fillable(['gateway', 'event_id', 'type', 'payload', 'processed_at', 'error'])]
class WebhookEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
