<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Spatie\Activitylog\Models\Activity as BaseActivity;

/**
 * Registro do histórico de alterações (spatie/laravel-activitylog), isolado por empresa.
 *
 * Fora de uma requisição da empresa (jobs da fila), a empresa vem do item alterado.
 *
 * @property int|null $company_id
 */
class Activity extends BaseActivity
{
    use BelongsToCompany;

    protected static function booted(): void
    {
        static::creating(function (self $activity): void {
            if ($activity->company_id === null && $activity->subject !== null) {
                $activity->company_id = $activity->subject->getAttribute('company_id');
            }
        });
    }
}
