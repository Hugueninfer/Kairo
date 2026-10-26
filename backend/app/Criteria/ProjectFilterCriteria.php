<?php

namespace App\Criteria;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Prettus\Repository\Contracts\CriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;

class ProjectFilterCriteria implements CriteriaInterface
{
    public function __construct(
        private readonly ?ProjectStatus $status = null,
        private readonly ?int $clientId = null,
    ) {}

    /**
     * @param  Model|Builder<Model>  $model
     * @return Model|Builder<Model>
     */
    public function apply($model, RepositoryInterface $repository)
    {
        if ($this->status !== null) {
            $model = $model->where('status', $this->status);
        }

        if ($this->clientId !== null) {
            $model = $model->where('client_id', $this->clientId);
        }

        return $model;
    }
}
