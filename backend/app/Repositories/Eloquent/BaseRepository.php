<?php

namespace App\Repositories\Eloquent;

use App\Criteria\CompanyCriteria;
use App\Models\Concerns\BelongsToCompany;
use Prettus\Repository\Eloquent\BaseRepository as PrettusBaseRepository;

/**
 * Base de todos os repositories do Kairo.
 *
 * Em models de empresa (BelongsToCompany), aplica o CompanyCriteria em toda consulta,
 * então nenhum Service precisa lembrar de filtrar por empresa.
 */
abstract class BaseRepository extends PrettusBaseRepository
{
    public function boot(): void
    {
        if (in_array(BelongsToCompany::class, class_uses_recursive($this->model()), true)) {
            $this->pushCriteria(app(CompanyCriteria::class));
        }
    }
}
