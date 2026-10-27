<?php

namespace App\Repositories\Contracts;

use App\Enums\TimeEntryStatus;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Prettus\Repository\Contracts\RepositoryInterface;

interface TimeEntryRepository extends RepositoryInterface
{
    /**
     * Timer rodando do usuário na empresa ativa.
     */
    public function runningFor(User $user): ?TimeEntry;

    /**
     * Timer rodando do usuário em qualquer empresa (só pode haver um).
     */
    public function runningAnywhereFor(User $user): ?TimeEntry;

    /**
     * Timers rodando desde antes do instante dado, em todas as empresas.
     *
     * @return Collection<int, TimeEntry>
     */
    public function runningSince(Carbon $before): Collection;

    /**
     * Apontamentos que começaram no período, com usuário, projeto e tarefa.
     *
     * @return Collection<int, TimeEntry>
     */
    public function inPeriod(Carbon $from, Carbon $to, ?int $userId = null, ?int $projectId = null): Collection;

    /**
     * @param  list<string>  $ulids
     * @return Collection<int, TimeEntry>
     */
    public function findManyByUlid(array $ulids): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createEntry(array $attributes): TimeEntry;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateEntry(TimeEntry $entry, array $attributes): TimeEntry;

    public function stop(TimeEntry $entry, Carbon $endedAt): TimeEntry;

    /**
     * @param  Collection<int, TimeEntry>  $entries
     */
    public function review(Collection $entries, TimeEntryStatus $status, User $reviewer): int;

    public function deleteEntry(TimeEntry $entry): void;

    /**
     * Horas que podem ir para uma fatura: aprovadas, cobráveis, encerradas e ainda não
     * faturadas, do projeto e do período, com a tarefa.
     *
     * @return Collection<int, TimeEntry>
     */
    public function billableForInvoice(int $projectId, Carbon $from, Carbon $to): Collection;
}
