<?php

namespace App\Services\Boards;

use App\DTOs\Boards\BoardData;
use App\DTOs\Boards\BoardElementData;
use App\DTOs\Boards\BoardFilters;
use App\DTOs\Boards\BoardSyncData;
use App\Enums\Permission;
use App\Events\Boards\BoardElementsChanged;
use App\Exceptions\Boards\BoardTooLargeException;
use App\Models\Board;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\BoardElementRepository;
use App\Repositories\Contracts\BoardRepository;
use App\Repositories\Contracts\ProjectRepository;
use App\Support\TenantContext;
use App\Support\Transactions\TransactionManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * Boards colaborativos: cadastro, gravação em lote dos elementos (autosave do canvas),
 * presença em tempo real e link de leitura para o cliente.
 */
class BoardService
{
    /** Teto de elementos por board, para o canvas continuar leve. */
    public const MAX_ELEMENTS = 2000;

    public function __construct(
        private readonly BoardRepository $boards,
        private readonly BoardElementRepository $elements,
        private readonly ProjectRepository $projects,
        private readonly BoardVersionService $versions,
        private readonly TenantContext $tenant,
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

        $saved = $this->transaction->run(function () use ($editor, $board, $data, $ulids): Board {
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

        // Quem está com o board aberto recebe o lote; quem salvou já tem tudo na tela.
        event((new BoardElementsChanged(
            $board->ulid,
            ['id' => $editor->id, 'name' => $editor->name],
            array_map(fn (BoardElementData $element) => $element->toApi(), $data->upserts),
            $data->deletes,
        ))->dontBroadcastToCurrentUser());

        $this->versions->snapshotIfDue($saved);

        return $saved;
    }

    /**
     * Dados de quem entra no canal de presença do board, ou null se não pode entrar.
     * Só membros da empresa ativa que podem ver boards, e só boards dessa empresa.
     *
     * @return array{id: int, name: string, can_edit: bool}|null
     */
    public function presenceMember(User $user, string $boardUlid): ?array
    {
        if (! $this->tenant->has() || ! $user->can(Permission::BoardsView->value)) {
            return null;
        }

        $board = $this->boards->findByUlid(strtolower($boardUlid));

        if ($board === null || $board->company_id !== $this->tenant->id()) {
            return null;
        }

        return ['id' => $user->id, 'name' => $user->name, 'can_edit' => $user->can(Permission::BoardsManage->value)];
    }

    /**
     * Liga o link de leitura. Chamar de novo mantém o mesmo link.
     */
    public function share(Board $board): Board
    {
        if ($board->share_token !== null) {
            return $board;
        }

        return $this->boards->updateBoard($board, ['share_token' => Str::random(48), 'shared_at' => now()]);
    }

    /**
     * Desliga o link: quem tinha o endereço antigo perde o acesso.
     */
    public function unshare(Board $board): Board
    {
        return $this->boards->updateBoard($board, ['share_token' => null, 'shared_at' => null]);
    }

    /**
     * Board aberto pelo link de leitura, sem login.
     */
    public function openShared(string $token): Board
    {
        return $this->boards->findShared($token) ?? throw (new ModelNotFoundException)->setModel(Board::class);
    }

    private function project(string $ulid): Project
    {
        return $this->projects->findByUlid($ulid) ?? throw (new ModelNotFoundException)->setModel(Project::class, [$ulid]);
    }
}
