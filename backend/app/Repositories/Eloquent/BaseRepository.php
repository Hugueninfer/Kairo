<?php

namespace App\Repositories\Eloquent;

use App\Criteria\CompanyCriteria;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Prettus\Repository\Contracts\CriteriaInterface;
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

    /**
     * Consulta nova com os criteria fixos do repository (como o da empresa ativa) e os
     * criteria de filtro da chamada, sem deixar estado no repository para a próxima.
     *
     * @param  list<CriteriaInterface>  $criteria
     * @return Builder<Model>
     */
    protected function filteredQuery(array $criteria = []): Builder
    {
        /** @var Model $model */
        $model = $this->app->make($this->model());
        $query = $model->newQuery();

        foreach ([...$this->getCriteria()->all(), ...$criteria] as $criterion) {
            $query = $criterion->apply($query, $this);
        }

        return $query;
    }
}
