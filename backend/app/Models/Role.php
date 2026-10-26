<?php

namespace App\Models;

use App\Enums\SystemRole;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Papel de uma empresa (spatie/laravel-permission em modo teams, com company_id como time).
 *
 * Não usa o escopo global BelongsToCompany: o spatie monta um cache com os papéis de
 * todas as empresas e o escopo cortaria esse cache. O RoleRepository sempre filtra
 * pela empresa, e a busca pela rota ({role}) fica restrita à empresa ativa.
 *
 * @property int $id
 * @property int $company_id
 * @property SystemRole|null $system_key
 */
class Role extends SpatieRole
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'system_key' => SystemRole::class,
        ];
    }

    /**
     * Membros com este papel. Igual ao users() do spatie, mas sem depender do guard
     * da instância (que não existe numa query nova, como em withCount).
     *
     * @return MorphToMany<User, $this>
     */
    public function members(): MorphToMany
    {
        return $this->morphedByMany(User::class, 'model', 'model_has_roles', 'role_id', 'model_id');
    }

    /**
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->newQuery()
            ->where('company_id', app(TenantContext::class)->company()->id)
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }

    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }

    public function isOwnerRole(): bool
    {
        return $this->system_key === SystemRole::Owner;
    }
}
