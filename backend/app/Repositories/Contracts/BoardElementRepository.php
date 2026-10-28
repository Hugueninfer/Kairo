<?php

namespace App\Repositories\Contracts;

use App\Models\Board;
use Prettus\Repository\Contracts\RepositoryInterface;

interface BoardElementRepository extends RepositoryInterface
{
    /**
     * Quantos elementos o board teria depois de gravar $ulids (os que ainda não existem contam como novos).
     *
     * @param  list<string>  $ulids
     * @param  list<string>  $deleting
     */
    public function countAfterSync(Board $board, array $ulids, array $deleting): int;

    /**
     * Cria ou atualiza os elementos (pelo ULID dentro do board) e apaga os informados, numa transação.
     *
     * @param  list<array<string, mixed>>  $upserts
     * @param  list<string>  $deletes
     */
    public function syncElements(Board $board, array $upserts, array $deletes, ?int $userId): void;

    /**
     * Todos os elementos do board no formato da API (id, type, x, y, ..., props), na ordem de empilhamento.
     *
     * @return list<array<string, mixed>>
     */
    public function snapshotOf(Board $board): array;

    /**
     * @return list<string>
     */
    public function ulidsOf(Board $board): array;
}
