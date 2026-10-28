<?php

namespace App\Repositories\Contracts;

use App\Models\Board;
use App\Models\BoardVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Prettus\Repository\Contracts\RepositoryInterface;

interface BoardVersionRepository extends RepositoryInterface
{
    /**
     * Versões do board, da mais nova para a mais antiga, sem a cópia dos elementos.
     *
     * @return LengthAwarePaginator<int, BoardVersion>
     */
    public function paginateForBoard(Board $board, int $perPage): LengthAwarePaginator;

    public function findForBoard(Board $board, string $ulid): ?BoardVersion;

    public function latestFor(Board $board): ?BoardVersion;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createVersion(array $attributes): BoardVersion;

    /**
     * Apaga as versões automáticas mais antigas, deixando só as $keep mais novas.
     */
    public function pruneAutomatic(Board $board, int $keep): void;
}
