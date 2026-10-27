<?php

namespace App\Repositories\Eloquent;

use App\Enums\TimeEntryStatus;
use App\Models\TimeEntry;
use App\Models\User;
use App\Repositories\Contracts\TimeEntryRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class TimeEntryRepositoryEloquent extends BaseRepository implements TimeEntryRepository
{
    private const RELATIONS = ['user:id,name', 'project:id,ulid,name', 'task:id,ulid,title'];

    public function model(): string
    {
        return TimeEntry::class;
    }

    public function runningFor(User $user): ?TimeEntry
    {
        /** @var TimeEntry|null */
        return $this->filteredQuery()
            ->with(self::RELATIONS)
            ->where('user_id', $user->id)
            ->whereNull('ended_at')
            ->first();
    }

    public function runningAnywhereFor(User $user): ?TimeEntry
    {
        return TimeEntry::query()
            ->withoutGlobalScope('company')
            ->where('user_id', $user->id)
            ->whereNull('ended_at')
            ->first();
    }

    public function runningSince(Carbon $before): Collection
    {
        return TimeEntry::query()
            ->withoutGlobalScope('company')
            ->with(['user', 'project' => fn ($query) => $query->withoutGlobalScopes()->select(['id', 'name'])])
            ->whereNull('ended_at')
            ->where('started_at', '<', $before)
            ->get();
    }

    public function inPeriod(Carbon $from, Carbon $to, ?int $userId = null, ?int $projectId = null): Collection
    {
        /** @var Collection<int, TimeEntry> */
        return $this->filteredQuery()
            ->with(self::RELATIONS)
            ->whereBetween('started_at', [$from, $to])
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->when($projectId !== null, fn ($query) => $query->where('project_id', $projectId))
            ->orderBy('started_at')
            ->get();
    }

    public function findManyByUlid(array $ulids): Collection
    {
        /** @var Collection<int, TimeEntry> */
        return $this->filteredQuery()->whereIn('ulid', $ulids)->get();
    }

    public function createEntry(array $attributes): TimeEntry
    {
        return TimeEntry::query()->create($attributes)->load(self::RELATIONS);
    }

    public function updateEntry(TimeEntry $entry, array $attributes): TimeEntry
    {
        $entry->update($attributes);

        return $entry->load(self::RELATIONS);
    }

    public function stop(TimeEntry $entry, Carbon $endedAt): TimeEntry
    {
        $minutes = max(1, (int) round($entry->started_at->diffInSeconds($endedAt) / 60));

        $entry->forceFill(['ended_at' => $endedAt, 'duration_minutes' => $minutes])->save();

        return $entry;
    }

    public function review(Collection $entries, TimeEntryStatus $status, User $reviewer): int
    {
        if ($entries->isEmpty()) {
            return 0;
        }

        return TimeEntry::query()
            ->whereKey($entries->modelKeys())
            ->whereNotNull('ended_at')
            // Hora já faturada não muda mais de situação.
            ->whereNull('invoice_id')
            ->update(['status' => $status, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now()]);
    }

    public function deleteEntry(TimeEntry $entry): void
    {
        $entry->delete();
    }

    public function billableForInvoice(int $projectId, Carbon $from, Carbon $to): Collection
    {
        /** @var Collection<int, TimeEntry> */
        return $this->filteredQuery()
            ->with('task:id,title')
            ->where('project_id', $projectId)
            ->where('status', TimeEntryStatus::Approved)
            ->where('billable', true)
            ->whereNotNull('ended_at')
            ->whereNull('invoice_id')
            ->where('started_at', '>=', $from)
            ->where('started_at', '<', $to)
            ->orderBy('started_at')
            ->get();
    }
}
