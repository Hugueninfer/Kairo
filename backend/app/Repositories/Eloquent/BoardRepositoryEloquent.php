<?php

namespace App\Repositories\Eloquent;

use App\Criteria\SearchCriteria;
use App\Models\Board;
use App\Repositories\Contracts\BoardRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BoardRepositoryEloquent extends BaseRepository implements BoardRepository
{
    public function model(): string
    {
        return Board::class;
    }

    public function paginateFiltered(?string $search, ?int $projectId, int $perPage): LengthAwarePaginator
    {
        $query = $this->filteredQuery([new SearchCriteria($search, ['title'])]);

        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        }

        /** @var LengthAwarePaginator<int, Board> */
        return $query
            ->with(['project:id,ulid,name,deleted_at', 'creator:id,name'])
            ->withCount('elements')
            ->latest('updated_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findByUlid(string $ulid): ?Board
    {
        /** @var Board|null */
        return $this->filteredQuery()->where('ulid', $ulid)->first();
    }

    public function findById(int $id): ?Board
    {
        /** @var Board|null */
        return Board::query()->withoutGlobalScope('company')->find($id);
    }

    public function findShared(string $token): ?Board
    {
        /** @var Board|null */
        return Board::query()
            ->withoutGlobalScope('company')
            ->where('share_token', $token)
            ->with(['company:id,name', 'elements' => fn ($query) => $query->withoutGlobalScope('company')])
            ->first();
    }

    public function loadDetails(Board $board): Board
    {
        return $board->load(['project:id,ulid,name,deleted_at', 'creator:id,name', 'elements'])->loadCount('elements');
    }

    public function createBoard(array $attributes): Board
    {
        return $this->loadDetails(Board::query()->create($attributes));
    }

    public function updateBoard(Board $board, array $attributes): Board
    {
        $board->update($attributes);

        return $this->loadDetails($board);
    }

    public function deleteBoard(Board $board): void
    {
        $board->delete();
    }

    public function touch(Board $board): Board
    {
        $board->touch();

        return $board;
    }
}
