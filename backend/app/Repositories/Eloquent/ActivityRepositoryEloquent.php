<?php

namespace App\Repositories\Eloquent;

use App\Models\Activity;
use App\Models\User;
use App\Repositories\Contracts\ActivityRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class ActivityRepositoryEloquent extends BaseRepository implements ActivityRepository
{
    public function model(): string
    {
        return Activity::class;
    }

    public function paginateFiltered(?string $logName, ?int $causerId, ?Carbon $from, ?Carbon $to, int $perPage): LengthAwarePaginator
    {
        $query = $this->filteredQuery()->with('causer:id,name');

        if ($logName !== null) {
            $query->where('log_name', $logName);
        }

        if ($causerId !== null) {
            $query->where('causer_type', (new User)->getMorphClass())->where('causer_id', $causerId);
        }

        if ($from !== null) {
            $query->where('created_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('created_at', '<', $to);
        }

        /** @var LengthAwarePaginator<int, Activity> */
        return $query->latest('created_at')->latest('id')->paginate($perPage)->withQueryString();
    }
}
