<?php

namespace App\Repositories\Eloquent;

use App\Criteria\CompanyCriteria;
use App\Models\Company;
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

    /**
     * Próximo número sequencial da empresa com o prefixo (ex.: ORC-2026-0007).
     * Trava a linha da empresa para dois envios simultâneos não pegarem o mesmo número;
     * precisa rodar dentro de uma transação. Conta também os registros apagados.
     */
    protected function nextSequence(int $companyId, string $prefix): string
    {
        Company::query()->whereKey($companyId)->lockForUpdate()->first();

        /** @var Model $model */
        $model = $this->app->make($this->model());
        $last = $model->newQueryWithoutScopes()
            ->where('company_id', $companyId)
            ->where('number', 'like', $prefix.'%')
            ->max('number');

        $sequence = is_string($last) ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
