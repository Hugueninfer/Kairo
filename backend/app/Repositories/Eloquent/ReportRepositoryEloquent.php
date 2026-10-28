<?php

namespace App\Repositories\Eloquent;

use App\Enums\InvoiceStatus;
use App\Enums\ProjectStatus;
use App\Enums\TimeEntryStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Repositories\Contracts\ReportRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ReportRepositoryEloquent extends BaseRepository implements ReportRepository
{
    public function model(): string
    {
        return TimeEntry::class;
    }

    public function minutesBetween(Carbon $from, Carbon $to, ?int $userId = null): array
    {
        $query = $this->counted($from, $to);

        if ($userId !== null) {
            $query->where('time_entries.user_id', $userId);
        }

        $row = $query
            ->selectRaw('coalesce(sum(time_entries.duration_minutes), 0) as total')
            ->selectRaw('coalesce(sum(case when time_entries.billable = ? then time_entries.duration_minutes else 0 end), 0) as billable', [true])
            ->toBase()
            ->first();

        return ['total' => (int) ($row->total ?? 0), 'billable' => (int) ($row->billable ?? 0)];
    }

    public function hoursBy(string $group, Carbon $from, Carbon $to): array
    {
        $query = $this->counted($from, $to);

        [$key, $label] = match ($group) {
            'member' => ['users.id', 'users.name'],
            'client' => ['clients.ulid', 'clients.name'],
            default => ['projects.ulid', 'projects.name'],
        };

        if ($group === 'member') {
            $query->join('users', 'users.id', '=', 'time_entries.user_id');
        } else {
            $query->join('projects', 'projects.id', '=', 'time_entries.project_id');
        }

        if ($group === 'client') {
            $query->leftJoin('clients', 'clients.id', '=', 'projects.client_id');
        }

        $rows = $query
            ->select(["{$key} as key", "{$label} as label"])
            ->selectRaw('sum(time_entries.duration_minutes) as minutes')
            ->selectRaw('sum(case when time_entries.billable = ? then time_entries.duration_minutes else 0 end) as billable_minutes', [true])
            ->selectRaw('sum(case when time_entries.billable = ? then time_entries.duration_minutes * coalesce(time_entries.hourly_rate, 0) else 0 end) as amount_minutes', [true])
            ->groupBy([$key, $label])
            ->orderByDesc('minutes')
            ->toBase()
            ->get();

        return $rows->map(fn (object $row) => [
            'key' => $row->key === null ? '' : (string) $row->key,
            'label' => $row->label === null ? 'Sem cliente' : (string) $row->label,
            'minutes' => (int) $row->minutes,
            'billable_minutes' => (int) $row->billable_minutes,
            'amount' => number_format((float) $row->amount_minutes / 60, 2, '.', ''),
        ])->values()->all();
    }

    public function projectsOverBudget(float $ratio, string $defaultRate): array
    {
        $consumed = TimeEntry::query()
            ->selectRaw('coalesce(sum(time_entries.duration_minutes * coalesce(time_entries.hourly_rate, projects.hourly_rate, ?)), 0) / 60', [$defaultRate])
            ->whereColumn('time_entries.project_id', 'projects.id')
            ->where('time_entries.status', '!=', TimeEntryStatus::Rejected);

        return Project::query()
            ->where('status', ProjectStatus::Active)
            ->where('budget', '>', 0)
            ->select(['projects.ulid', 'projects.name', 'projects.budget'])
            ->selectSub($consumed, 'consumed')
            ->get()
            ->map(function (Project $project) {
                $consumed = (float) $project->getAttribute('consumed');
                $budget = (float) $project->budget;

                return [
                    'id' => $project->ulid,
                    'name' => $project->name,
                    'budget' => number_format($budget, 2, '.', ''),
                    'consumed' => number_format($consumed, 2, '.', ''),
                    'percent' => (int) floor($consumed / $budget * 100),
                ];
            })
            ->filter(fn (array $project) => $project['percent'] >= $ratio * 100)
            ->sortByDesc('percent')
            ->values()
            ->all();
    }

    public function paymentsSince(Carbon $from): array
    {
        return Payment::query()
            ->where('paid_at', '>=', $from)
            ->get(['paid_at', 'amount'])
            ->map(fn (Payment $payment) => ['paid_at' => $payment->paid_at, 'amount' => (string) $payment->amount])
            ->values()
            ->all();
    }

    public function receivables(): array
    {
        $row = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::PartiallyPaid, InvoiceStatus::Overdue])
            ->selectRaw('count(*) as open_count')
            ->selectRaw('coalesce(sum(total - amount_paid), 0) as open_total')
            ->selectRaw('coalesce(sum(case when status = ? then 1 else 0 end), 0) as overdue_count', [InvoiceStatus::Overdue->value])
            ->selectRaw('coalesce(sum(case when status = ? then total - amount_paid else 0 end), 0) as overdue_total', [InvoiceStatus::Overdue->value])
            ->toBase()
            ->first();

        return [
            'open_count' => (int) ($row->open_count ?? 0),
            'open_total' => number_format((float) ($row->open_total ?? 0), 2, '.', ''),
            'overdue_count' => (int) ($row->overdue_count ?? 0),
            'overdue_total' => number_format((float) ($row->overdue_total ?? 0), 2, '.', ''),
        ];
    }

    public function hoursFingerprint(): string
    {
        return $this->fingerprintOf(TimeEntry::query());
    }

    public function dashboardFingerprint(): string
    {
        return implode('/', [
            $this->hoursFingerprint(),
            $this->fingerprintOf(Invoice::query()),
            $this->fingerprintOf(Payment::query()),
            $this->fingerprintOf(Project::query()),
        ]);
    }

    /**
     * Quantidade de linhas e a última alteração: muda quando algo é criado, alterado ou apagado.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private function fingerprintOf(Builder $query): string
    {
        $row = $query
            ->selectRaw('count(*) as entries')
            ->selectRaw('max(updated_at) as last_change')
            ->toBase()
            ->first();

        return ($row->entries ?? 0).'|'.($row->last_change ?? '');
    }

    /**
     * Horas que contam nos números: do período, encerradas e não recusadas.
     *
     * @return Builder<TimeEntry>
     */
    private function counted(Carbon $from, Carbon $to): Builder
    {
        return TimeEntry::query()
            ->where('time_entries.started_at', '>=', $from)
            ->where('time_entries.started_at', '<', $to)
            ->whereNotNull('time_entries.ended_at')
            ->where('time_entries.status', '!=', TimeEntryStatus::Rejected);
    }
}
