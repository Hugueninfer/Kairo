<?php

namespace App\Services\Audit;

use App\DTOs\Audit\AuditFilters;
use App\Enums\AuditSubject;
use App\Models\Activity;
use App\Models\Company;
use App\Repositories\Contracts\ActivityRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Histórico de quem fez o quê na empresa.
 *
 * Cadastros (clientes, projetos, faturas...) entram sozinhos pelo trait LogsCompanyActivity.
 * O que não é um cadastro simples (papéis e equipe) é registrado aqui pelos services.
 */
class AuditService
{
    public function __construct(private readonly ActivityRepository $activities) {}

    /**
     * @return LengthAwarePaginator<int, Activity>
     */
    public function list(Company $company, AuditFilters $filters): LengthAwarePaginator
    {
        // O período vem em dias do fuso da empresa.
        $from = $filters->from === null ? null : Carbon::parse($filters->from, $company->timezone)->startOfDay()->utc();
        $to = $filters->to === null ? null : Carbon::parse($filters->to, $company->timezone)->addDay()->startOfDay()->utc();

        return $this->activities->paginateFiltered($filters->subject?->value, $filters->userId, $from, $to, $filters->perPage);
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function record(int $companyId, AuditSubject $subject, string $event, string $label, array $old = [], array $new = []): void
    {
        $properties = ['label' => $label];

        if ($new !== []) {
            $properties['attributes'] = $new;
        }

        if ($old !== []) {
            $properties['old'] = $old;
        }

        activity($subject->value)
            ->event($event)
            ->withProperties($properties)
            ->tap(fn (Activity $activity) => $activity->company_id = $companyId)
            ->log($event);
    }
}
