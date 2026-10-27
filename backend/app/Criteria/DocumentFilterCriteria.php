<?php

namespace App\Criteria;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Prettus\Repository\Contracts\CriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Filtro por situação e cliente, comum a orçamentos e contratos.
 */
class DocumentFilterCriteria implements CriteriaInterface
{
    public function __construct(
        private readonly ?BackedEnum $status = null,
        private readonly ?int $clientId = null,
    ) {}

    /**
     * @param  Model|Builder<Model>  $model
     * @return Model|Builder<Model>
     */
    public function apply($model, RepositoryInterface $repository)
    {
        if ($this->status !== null) {
            $model = $model->where('status', $this->status->value);
        }

        if ($this->clientId !== null) {
            $model = $model->where('client_id', $this->clientId);
        }

        return $model;
    }
}
