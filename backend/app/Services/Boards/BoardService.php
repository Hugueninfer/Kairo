<?php

namespace App\Services\Boards;

use App\DTOs\Boards\BoardData;
use App\DTOs\Boards\BoardElementData;
use App\DTOs\Boards\BoardFilters;
use App\DTOs\Boards\BoardSyncData;
use App\Exceptions\Boards\BoardTooLargeException;
use App\Models\Board;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\BoardElementRepository;
use App\Repositories\Contracts\BoardRepository;
use App\Repositories\Contracts\ProjectRepository;
use App\Support\Transactions\TransactionManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Boards colaborativos: cadastro e gravação em lote dos elementos (autosave do canvas).
 */
class BoardService
{
    /** Teto de elementos por board, para o canvas continuar leve. */
    public const MAX_ELEMENTS = 2000;

    public function __construct(
        private readonly BoardRepository $boards,
        private readonly BoardElementRepository $elements,
        private readonly ProjectRepository $projects,
        private readonly TransactionManager $transaction,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Board>
     */
    public function list(BoardFilters $filters): LengthAwarePaginator
    {
        $projectId = $filters->project === null ? null : $this->project($filters->project)->id;

        return $this->boards->paginateFiltered($filters->search, $projectId, $filters->perPage);
    }

    public function show(Board $board): Board
    {
        return $this->boards->loadDetails($board);
    }

    public function create(User $author, BoardData $data): Board
    {
        return $this->boards->createBoard([
            'title' => $data->title,
            'project_id' => $data->project === null ? null : $this->project($data->project)->id,
            'created_by' => $author->id,
        ]);
    }

    public function update(Board $board, BoardData $data): Board
    {
        return $this->boards->updateBoard($board, [
            'title' => $data->title,
            'project_id' => $data->project === null ? null : $this->project($data->project)->id,
        ]);
    }

    public function delete(Board $board): void
    {
        $this->boards->deleteBoard($board);
    }

    /**
     * Grava o lote do autosave. O mesmo lote enviado duas vezes dá o mesmo resultado.
     */
    public function sync(User $editor, Board $board, BoardSyncData $data): Board
    {
        $ulids = array_map(fn (BoardElementData $element) => $element->ulid, $data->upserts);

        return $this->transaction->run(function () use ($editor, $board, $data, $ulids): Board {
            if ($this->elements->countAfterSync($board, $ulids, $data->deletes) > self::MAX_ELEMENTS) {
                throw new BoardTooLargeException(self::MAX_ELEMENTS);
            }

            $this->elements->syncElements(
                $board,
                array_map(fn (BoardElementData $element) => $element->toArray(), $data->upserts),
                $data->deletes,
                $editor->id,
            );

            return $this->boards->touch($board);
        });
    }

    private function project(string $ulid): Project
    {
        return $this->projects->findByUlid($ulid) ?? throw (new ModelNotFoundException)->setModel(Project::class, [$ulid]);
    }
}
