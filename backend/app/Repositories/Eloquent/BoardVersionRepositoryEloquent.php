<?php

namespace App\Repositories\Eloquent;

use App\Models\Board;
use App\Models\BoardVersion;
use App\Repositories\Contracts\BoardVersionRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class BoardVersionRepositoryEloquent extends BaseRepository implements BoardVersionRepository
{
    public function model(): string
    {
        return BoardVersion::class;
    }

    public function paginateForBoard(Board $board, int $perPage): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, BoardVersion> */
        return $this->ofBoard($board)
            ->select(['id', 'ulid', 'board_id', 'created_by', 'label', 'automatic', 'element_count', 'created_at'])
            ->with('author:id,name')
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage);
    }

    public function findForBoard(Board $board, string $ulid): ?BoardVersion
    {
        /** @var BoardVersion|null */
        return $this->ofBoard($board)->where('ulid', $ulid)->with('author:id,name')->first();
    }

    public function latestFor(Board $board): ?BoardVersion
    {
        /** @var BoardVersion|null */
        return $this->ofBoard($board)
            ->select(['id', 'board_id', 'automatic', 'content_hash', 'created_at'])
            ->latest('created_at')
            ->latest('id')
            ->first();
    }

    public function createVersion(array $attributes): BoardVersion
    {
        return BoardVersion::query()->create($attributes);
    }

    public function pruneAutomatic(Board $board, int $keep): void
    {
        $kept = $this->ofBoard($board)->where('automatic', true)->latest('created_at')->latest('id')->limit($keep)->pluck('id');

        $this->ofBoard($board)->where('automatic', true)->whereNotIn('id', $kept)->delete();
    }

    /**
     * O board já foi conferido (rota da empresa ou job), então não depende da empresa ativa.
     *
     * @return Builder<BoardVersion>
     */
    private function ofBoard(Board $board): Builder
    {
        return BoardVersion::query()->withoutGlobalScope('company')->where('board_id', $board->id);
    }
}
