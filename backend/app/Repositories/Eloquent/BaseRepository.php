<?php

namespace App\Repositories\Eloquent;

use Prettus\Repository\Eloquent\BaseRepository as PrettusBaseRepository;

/**
 * Base de todos os repositories do Kairo.
 *
 * Concentra comportamentos comuns a todas as consultas. Na fase de multi-tenant,
 * é aqui que o CompanyCriteria passa a ser aplicado automaticamente.
 */
abstract class BaseRepository extends PrettusBaseRepository
{
    public function boot(): void
    {
        //
    }
}
