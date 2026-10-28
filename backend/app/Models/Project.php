<?php

namespace App\Models;

use App\Enums\AuditSubject;
use App\Enums\BillingType;
use App\Enums\ProjectStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\LogsCompanyActivity;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property ProjectStatus $status
 * @property BillingType $billing_type
 * @property string|null $hourly_rate
 * @property string|null $budget
 * @property Carbon|null $starts_on
 * @property Carbon|null $due_on
 */
#[Fillable(['client_id', 'name', 'description', 'status', 'billing_type', 'hourly_rate', 'budget', 'starts_on', 'due_on'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use BelongsToCompany, HasFactory, HasUlids, LogsCompanyActivity, SoftDeletes;

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
            'status' => ProjectStatus::class,
            'billing_type' => BillingType::class,
            'hourly_rate' => 'decimal:2',
            'budget' => 'decimal:2',
            'starts_on' => 'date',
            'due_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public static function auditSubject(): AuditSubject
    {
        return AuditSubject::Project;
    }

    /**
     * @return list<string>
     */
    protected function auditedAttributes(): array
    {
        return ['name', 'status', 'billing_type', 'hourly_rate', 'budget', 'starts_on', 'due_on'];
    }

    public function auditLabel(): string
    {
        return $this->name;
    }
}
