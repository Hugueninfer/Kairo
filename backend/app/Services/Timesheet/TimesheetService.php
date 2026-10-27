<?php

namespace App\Services\Timesheet;

use App\DTOs\Timesheet\TimeEntryFilters;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Repositories\Contracts\ProjectRepository;
use App\Repositories\Contracts\TimeEntryRepository;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;

/**
 * Leitura das horas: lista do período, planilha semanal e exportação.
 * As datas são sempre interpretadas no fuso da empresa.
 */
class TimesheetService
{
    public function __construct(
        private readonly TimeEntryRepository $entries,
        private readonly ProjectRepository $projects,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @return Collection<int, TimeEntry>
     */
    public function entries(TimeEntryFilters $filters): Collection
    {
        $timezone = $this->timezone();
        $projectId = null;

        if ($filters->project !== null) {
            $project = $this->projects->findByUlid($filters->project)
                ?? throw (new ModelNotFoundException)->setModel(Project::class, [$filters->project]);
            $projectId = $project->id;
        }

        return $this->entries->inPeriod(
            Carbon::parse($filters->from, $timezone)->startOfDay()->utc(),
            Carbon::parse($filters->to, $timezone)->endOfDay()->utc(),
            $filters->userId,
            $projectId,
        );
    }

    /**
     * Planilha da semana (segunda a domingo): uma linha por projeto/tarefa, minutos por dia e totais.
     *
     * @return array{
     *     week_start: string,
     *     days: list<string>,
     *     rows: list<array{project: array{id: string, name: string}, task: array{id: string, title: string}|null, minutes: list<int>, total: int}>,
     *     totals: array{minutes: list<int>, total: int, billable_minutes: int, amount: string}
     * }
     */
    public function week(string $anyDay, ?int $userId): array
    {
        $timezone = $this->timezone();
        $start = Carbon::parse($anyDay, $timezone)->startOfWeek(Carbon::MONDAY);
        $days = array_map(fn (int $offset) => $start->copy()->addDays($offset)->toDateString(), range(0, 6));

        $entries = $this->entries(new TimeEntryFilters($days[0], $days[6], $userId));

        $rows = [];
        $dayTotals = array_fill(0, 7, 0);
        $billable = 0;
        $amount = 0.0;

        foreach ($entries as $entry) {
            if ($entry->isRunning()) {
                continue;
            }

            $day = array_search($entry->started_at->copy()->setTimezone($timezone)->toDateString(), $days, true);

            if ($day === false) {
                continue;
            }

            $key = $entry->project_id.':'.($entry->task_id ?? '-');
            $rows[$key] ??= [
                'project' => ['id' => $entry->project->ulid, 'name' => $entry->project->name],
                'task' => $entry->task === null ? null : ['id' => $entry->task->ulid, 'title' => $entry->task->title],
                'minutes' => array_fill(0, 7, 0),
                'total' => 0,
            ];

            $rows[$key]['minutes'][$day] += $entry->duration_minutes;
            $rows[$key]['total'] += $entry->duration_minutes;
            $dayTotals[$day] += $entry->duration_minutes;

            if ($entry->billable) {
                $billable += $entry->duration_minutes;
                $amount += (float) ($entry->amount() ?? 0);
            }
        }

        $rows = array_values($rows);
        usort($rows, fn (array $a, array $b) => [$a['project']['name'], $a['task']['title'] ?? ''] <=> [$b['project']['name'], $b['task']['title'] ?? '']);

        return [
            'week_start' => $days[0],
            'days' => $days,
            'rows' => $rows,
            'totals' => [
                'minutes' => $dayTotals,
                'total' => array_sum($dayTotals),
                'billable_minutes' => $billable,
                'amount' => number_format($amount, 2, '.', ''),
            ],
        ];
    }

    /**
     * Linhas do CSV (cabeçalho incluso), uma por apontamento encerrado.
     *
     * @return iterable<list<string>>
     */
    public function csvRows(TimeEntryFilters $filters): iterable
    {
        $timezone = $this->timezone();

        yield ['Data', 'Início', 'Fim', 'Pessoa', 'Projeto', 'Tarefa', 'Descrição', 'Horas', 'Cobrável', 'Valor/hora', 'Valor', 'Situação'];

        foreach ($this->entries($filters) as $entry) {
            if ($entry->isRunning()) {
                continue;
            }

            $start = $entry->started_at->copy()->setTimezone($timezone);

            yield [
                $start->format('d/m/Y'),
                $start->format('H:i'),
                $entry->ended_at?->copy()->setTimezone($timezone)->format('H:i') ?? '',
                $entry->user->name,
                $entry->project->name,
                $entry->task->title ?? '',
                $entry->description ?? '',
                number_format($entry->duration_minutes / 60, 2, ',', ''),
                $entry->billable ? 'Sim' : 'Não',
                $entry->hourly_rate === null ? '' : number_format((float) $entry->hourly_rate, 2, ',', ''),
                $entry->amount() === null ? '' : number_format((float) $entry->amount(), 2, ',', ''),
                $entry->status->label(),
            ];
        }
    }

    private function timezone(): string
    {
        return $this->tenant->company()->timezone;
    }
}
