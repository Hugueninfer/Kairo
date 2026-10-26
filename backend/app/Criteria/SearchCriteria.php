<?php

namespace App\Criteria;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Prettus\Repository\Contracts\CriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Busca textual simples (sem diferenciar maiúsculas) em uma ou mais colunas.
 */
class SearchCriteria implements CriteriaInterface
{
    /**
     * @param  list<string>  $columns
     */
    public function __construct(
        private readonly ?string $term,
        private readonly array $columns,
    ) {}

    /**
     * @param  Model|Builder<Model>  $model
     * @return Model|Builder<Model>
     */
    public function apply($model, RepositoryInterface $repository)
    {
        $term = trim((string) $this->term);

        if ($term === '') {
            return $model;
        }

        // Escapa os curingas do LIKE para o termo ser buscado como texto.
        $like = '%'.addcslashes(mb_strtolower($term), '%_\\').'%';

        return $model->where(function ($query) use ($like): void {
            foreach ($this->columns as $column) {
                $query->orWhereRaw('lower('.$query->getModel()->qualifyColumn($column).') like ?', [$like]);
            }
        });
    }
}
