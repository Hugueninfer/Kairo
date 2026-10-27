<?php

namespace App\Models;

use App\Enums\TimeEntryStatus;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Apontamento de horas. Com ended_at vazio, é um timer rodando.
 *
 * @property int $user_id
 * @property int $project_id
 * @property int|null $task_id
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property int $duration_minutes
 * @property bool $billable
 * @property string|null $hourly_rate
 * @property TimeEntryStatus $status
 * @property Carbon|null $reviewed_at
 * @property int|null $invoice_id
 */
#[Fillable(['user_id', 'project_id', 'task_id', 'description', 'started_at', 'ended_at', 'duration_minutes', 'billable', 'hourly_rate', 'status', 'reviewed_by', 'reviewed_at'])]
class TimeEntry extends Model
{
    /** @use HasFactory<TimeEntryFactory> */
    use BelongsToCompany, HasFactory, HasUlids;

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
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'billable' => 'boolean',
            'hourly_rate' => 'decimal:2',
            'status' => TimeEntryStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * Valor do apontamento (horas x valor/hora), em reais com centavos.
     */
    public function amount(): ?string
    {
        if (! $this->billable || $this->hourly_rate === null) {
            return null;
        }

        return number_format($this->duration_minutes / 60 * (float) $this->hourly_rate, 2, '.', '');
    }
}
