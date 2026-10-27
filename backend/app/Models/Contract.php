<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Enums\RecurrenceFrequency;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Nasce do aceite de um orçamento e guarda o registro de quem aceitou.
 *
 * @property ContractStatus $status
 * @property string $total
 * @property Carbon $signed_at
 * @property RecurrenceFrequency|null $billing_frequency
 * @property string|null $recurring_amount
 * @property Carbon|null $next_billing_on
 * @property Carbon|null $billing_ends_on
 * @property int $payment_term_days
 * @property bool $auto_send
 */
#[Fillable([
    'client_id', 'quote_id', 'project_id', 'number', 'title', 'total', 'terms', 'status', 'signed_at', 'signer_name', 'signer_ip',
    'billing_frequency', 'recurring_amount', 'next_billing_on', 'billing_ends_on', 'payment_term_days', 'auto_send',
])]
class Contract extends Model
{
    use BelongsToCompany, HasUlids;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContractStatus::class,
            'total' => 'decimal:2',
            'signed_at' => 'datetime',
            'billing_frequency' => RecurrenceFrequency::class,
            'recurring_amount' => 'decimal:2',
            'next_billing_on' => 'date',
            'billing_ends_on' => 'date',
            'payment_term_days' => 'integer',
            'auto_send' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
