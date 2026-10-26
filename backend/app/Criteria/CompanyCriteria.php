<?php

namespace App\Criteria;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Prettus\Repository\Contracts\CriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Filtra pela empresa ativa. Aplicado automaticamente pelo BaseRepository em todo
 * model que usa BelongsToCompany, como segunda camada além do escopo global.
 */
class CompanyCriteria implements CriteriaInterface
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @param  Model|Builder<Model>  $model
     * @return Model|Builder<Model>
     */
    public function apply($model, RepositoryInterface $repository)
    {
        if (! $this->tenant->has()) {
            return $model;
        }

        $column = ($model instanceof Builder ? $model->getModel() : $model)->qualifyColumn('company_id');

        return $model->where($column, $this->tenant->id());
    }
}
