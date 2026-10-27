<?php

namespace App\Repositories\Contracts;

use App\Models\Board;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Prettus\Repository\Contracts\RepositoryInterface;

interface BoardRepository extends RepositoryInterface
{
    /**
     * Boards da empresa ativa, os mexidos por último primeiro, com a contagem de elementos.
     *
     * @return LengthAwarePaginator<int, Board>
     */
    public function paginateFiltered(?string $search, ?int $projectId, int $perPage): LengthAwarePaginator;

    public function findByUlid(string $ulid): ?Board;

    /**
     * Carrega projeto e elementos (na ordem de empilhamento).
     */
    public function loadDetails(Board $board): Board;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createBoard(array $attributes): Board;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateBoard(Board $board, array $attributes): Board;

    public function deleteBoard(Board $board): void;

    public function touch(Board $board): Board;
}
