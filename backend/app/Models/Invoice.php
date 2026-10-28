<?php

namespace App\Models;

use App\Enums\AuditSubject;
use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\LogsCompanyActivity;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property InvoiceStatus $status
 * @property Carbon $issue_date
 * @property Carbon $due_date
 * @property Carbon|null $billing_period_start
 * @property string $subtotal
 * @property string $discount
 * @property string $total
 * @property string $amount_paid
 * @property Carbon|null $sent_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $cancelled_at
 */
#[Fillable([
    'client_id', 'project_id', 'contract_id', 'billing_period_start', 'created_by', 'number', 'status', 'issue_date', 'due_date', 'notes',
    'subtotal', 'discount', 'total', 'amount_paid', 'gateway', 'gateway_charge_id', 'payment_url', 'pdf_path',
    'sent_at', 'paid_at', 'cancelled_at',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToCompany, HasFactory, HasUlids, LogsCompanyActivity;

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
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'billing_period_start' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_at');
    }

    /**
     * @return HasMany<InvoiceReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(InvoiceReminder::class)->orderBy('sent_at');
    }

    /**
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public static function auditSubject(): AuditSubject
    {
        return AuditSubject::Invoice;
    }

    /**
     * @return list<string>
     */
    protected function auditedAttributes(): array
    {
        return ['number', 'status', 'due_date', 'total', 'amount_paid'];
    }

    public function auditLabel(): string
    {
        return $this->number;
    }
}
