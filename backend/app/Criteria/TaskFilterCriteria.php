<?php

namespace App\Criteria;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Prettus\Repository\Contracts\CriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;

class TaskFilterCriteria implements CriteriaInterface
{
    public function __construct(
        private readonly ?int $projectId = null,
        private readonly ?int $assigneeId = null,
        private readonly ?bool $open = null,
    ) {}

    /**
     * @param  Model|Builder<Model>  $model
     * @return Model|Builder<Model>
     */
    public function apply($model, RepositoryInterface $repository)
    {
        if ($this->projectId !== null) {
            $model = $model->where('project_id', $this->projectId);
        }

        if ($this->assigneeId !== null) {
            $model = $model->where('assignee_id', $this->assigneeId);
        }

        if ($this->open === true) {
            $model = $model->where('status', '!=', TaskStatus::Done);
        }

        return $model;
    }
}
