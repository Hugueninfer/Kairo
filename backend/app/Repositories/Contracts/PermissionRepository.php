<?php

namespace App\Repositories\Contracts;

use Prettus\Repository\Contracts\RepositoryInterface;

interface PermissionRepository extends RepositoryInterface
{
    /**
     * Garante que todas as permissões do catálogo existem no banco. Idempotente.
     *
     * @param  list<string>  $names
     */
    public function syncCatalog(array $names): void;
}
