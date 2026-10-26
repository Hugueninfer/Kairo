<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Isola o model por empresa: filtra todas as consultas pela empresa ativa e
 * preenche company_id ao criar. O company_id nunca vem do front.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $query): void {
            $tenant = app(TenantContext::class);

            if ($tenant->has()) {
                $query->where($query->qualifyColumn('company_id'), $tenant->id());
            }
        });

        static::creating(function (self $model): void {
            $tenant = app(TenantContext::class);

            if ($tenant->has() && $model->getAttribute('company_id') === null) {
                $model->setAttribute('company_id', $tenant->id());
            }
        });
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
