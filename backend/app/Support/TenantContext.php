<?php

namespace App\Support;

use App\Exceptions\Companies\NoActiveCompanyException;
use App\Models\Company;
use Spatie\Permission\PermissionRegistrar;

/**
 * Empresa ativa da requisição (ou do job). Registrado como singleton no container.
 *
 * Preenchido pelo middleware ResolveTenant nas rotas da empresa e pelos jobs que
 * restauram o contexto. Sem empresa definida (console, rotas públicas), o escopo
 * de tenant não filtra nada.
 *
 * Também define o "time" do spatie/laravel-permission, para os papéis e permissões
 * consultados na requisição serem os da empresa ativa.
 */
class TenantContext
{
    private ?Company $company = null;

    public function set(Company $company): void
    {
        $this->company = $company;
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
    }

    public function forget(): void
    {
        $this->company = null;
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    public function has(): bool
    {
        return $this->company !== null;
    }

    public function company(): Company
    {
        return $this->company ?? throw new NoActiveCompanyException;
    }

    public function id(): ?int
    {
        return $this->company?->id;
    }
}
