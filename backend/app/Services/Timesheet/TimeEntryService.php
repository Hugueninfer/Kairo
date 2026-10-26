<?php

namespace App\Services\Timesheet;

use App\DTOs\Timesheet\TimeEntryData;
use App\DTOs\Timesheet\TimerData;
use App\Enums\BillingType;
use App\Enums\TimeEntryStatus;
use App\Exceptions\Projects\ProjectClosedException;
use App\Exceptions\Tasks\TaskNotInProjectException;
use App\Exceptions\Timesheet\NotEntryOwnerException;
use App\Exceptions\Timesheet\TimeEntryLockedException;
use App\Exceptions\Timesheet\TimeEntryRunningException;
use App\Exceptions\Timesheet\TimerNotRunningException;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Repositories\Contracts\ProjectRepository;
use App\Repositories\Contracts\TaskRepository;
use App\Repositories\Contracts\TimeEntryRepository;
use App\Support\TenantContext;
use App\Support\Transactions\TransactionManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;

/**
 * Apontamentos de horas: timer, lançamento manual, edição e aprovação.
 */
class TimeEntryService
{
    public function __construct(
        private readonly TimeEntryRepository $entries,
        private readonly ProjectRepository $projects,
        private readonly TaskRepository $tasks,
        private readonly TenantContext $tenant,
        private readonly TransactionManager $transaction,
    ) {}

    public function current(User $user): ?TimeEntry
    {
        return $this->entries->runningFor($user);
    }

    /**
     * Inicia um timer. Se já houver outro rodando (em qualquer empresa), ele é parado antes.
     */
    public function start(User $user, TimerData $data): TimeEntry
    {
        [$project, $task] = $this->resolve($data->project, $data->task);

        return $this->transaction->run(function () use ($user, $project, $task, $data): TimeEntry {
            $running = $this->entries->runningAnywhereFor($user);

            if ($running !== null) {
                $this->entries->stop($running, now());
            }

            return $this->entries->createEntry([
                ...$this->billing($project),
                'user_id' => $user->id,
                'project_id' => $project->id,
                'task_id' => $task?->id,
                'description' => $data->description,
                'started_at' => now(),
                'status' => TimeEntryStatus::Pending,
            ]);
        });
    }

    public function stop(User $user): TimeEntry
    {
        $running = $this->entries->runningFor($user) ?? throw new TimerNotRunningException;

        return $this->entries->stop($running, now());
    }

    public function create(User $user, TimeEntryData $data): TimeEntry
    {
        [$project, $task] = $this->resolve($data->project, $data->task);

        return $this->entries->createEntry([
            ...$this->billing($project),
            ...$this->period($data),
            ...$this->billableOverride($data),
            'user_id' => $user->id,
            'project_id' => $project->id,
            'task_id' => $task?->id,
            'description' => $data->description,
            'status' => TimeEntryStatus::Pending,
        ]);
    }

    /**
     * Editar um apontamento recusado devolve ele para aprovação.
     */
    public function update(User $user, TimeEntry $entry, TimeEntryData $data): TimeEntry
    {
        $this->ensureEditable($user, $entry);
        [$project, $task] = $this->resolve($data->project, $data->task);

        $billing = $project->id === $entry->project_id ? [] : $this->billing($project);

        return $this->entries->updateEntry($entry, [
            ...$billing,
            ...$this->period($data),
            ...$this->billableOverride($data),
            'project_id' => $project->id,
            'task_id' => $task?->id,
            'description' => $data->description,
            'status' => TimeEntryStatus::Pending,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);
    }

    public function delete(User $user, TimeEntry $entry): void
    {
        if ($entry->user_id !== $user->id) {
            throw new NotEntryOwnerException;
        }

        if ($entry->status->isLocked()) {
            throw new TimeEntryLockedException;
        }

        $this->entries->deleteEntry($entry);
    }

    /**
     * Aprova ou recusa vários apontamentos de uma vez. Timers rodando são ignorados.
     *
     * @param  list<string>  $ulids
     */
    public function review(User $reviewer, array $ulids, TimeEntryStatus $status): int
    {
        return $this->entries->review($this->entries->findManyByUlid($ulids), $status, $reviewer);
    }

    /**
     * Para timers esquecidos (rodando há mais tempo que o limite), fechando no limite.
     *
     * @return list<TimeEntry> os timers parados
     */
    public function stopForgotten(int $maxHours): array
    {
        $stopped = [];

        foreach ($this->entries->runningSince(now()->subHours($maxHours)) as $entry) {
            $stopped[] = $this->entries->stop($entry, $entry->started_at->copy()->addHours($maxHours));
        }

        return $stopped;
    }

    private function ensureEditable(User $user, TimeEntry $entry): void
    {
        if ($entry->user_id !== $user->id) {
            throw new NotEntryOwnerException;
        }

        if ($entry->isRunning()) {
            throw new TimeEntryRunningException;
        }

        if ($entry->status->isLocked()) {
            throw new TimeEntryLockedException;
        }
    }

    /**
     * @return array{0: Project, 1: Task|null}
     */
    private function resolve(string $projectUlid, ?string $taskUlid): array
    {
        $project = $this->projects->findByUlid($projectUlid)
            ?? throw (new ModelNotFoundException)->setModel(Project::class, [$projectUlid]);

        if (! $project->status->acceptsTasks()) {
            throw new ProjectClosedException;
        }

        if ($taskUlid === null) {
            return [$project, null];
        }

        $task = $this->tasks->findByUlid($taskUlid) ?? throw new TaskNotInProjectException;

        if ($task->project_id !== $project->id) {
            throw new TaskNotInProjectException;
        }

        return [$project, $task];
    }

    /**
     * Só projeto por hora gera valor. O valor/hora é o do projeto ou, sem ele, o da empresa.
     *
     * @return array{billable: bool, hourly_rate: string|null}
     */
    private function billing(Project $project): array
    {
        if ($project->billing_type !== BillingType::Hourly) {
            return ['billable' => false, 'hourly_rate' => null];
        }

        return [
            'billable' => true,
            'hourly_rate' => $project->hourly_rate ?? $this->tenant->company()->default_hourly_rate,
        ];
    }

    /**
     * @return array{billable?: bool}
     */
    private function billableOverride(TimeEntryData $data): array
    {
        return $data->billable === null ? [] : ['billable' => $data->billable];
    }

    /**
     * Início no fuso da empresa (09:00 quando não informado), gravado em UTC.
     *
     * @return array{started_at: Carbon, ended_at: Carbon, duration_minutes: int}
     */
    private function period(TimeEntryData $data): array
    {
        $start = Carbon::createFromFormat(
            'Y-m-d H:i',
            $data->date.' '.($data->start ?? '09:00'),
            $this->tenant->company()->timezone,
        );

        if ($start === null) {
            throw new \InvalidArgumentException('Data inválida.');
        }

        $start = $start->utc();

        return [
            'started_at' => $start,
            'ended_at' => $start->copy()->addMinutes($data->minutes),
            'duration_minutes' => $data->minutes,
        ];
    }
}
