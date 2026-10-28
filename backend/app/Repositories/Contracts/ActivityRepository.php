<?php

namespace App\Repositories\Contracts;

use App\Models\Activity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Prettus\Repository\Contracts\RepositoryInterface;

interface ActivityRepository extends RepositoryInterface
{
    /**
     * Histórico da empresa ativa, do mais recente para o mais antigo, com quem fez.
     *
     * @return LengthAwarePaginator<int, Activity>
     */
    public function paginateFiltered(?string $logName, ?int $causerId, ?Carbon $from, ?Carbon $to, int $perPage): LengthAwarePaginator;
}
