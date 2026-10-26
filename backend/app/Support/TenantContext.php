<?php

namespace App\Support;

use App\Exceptions\Companies\NoActiveCompanyException;
use App\Models\Company;

/**
 * Empresa ativa da requisição (ou do job). Registrado como singleton no container.
 *
 * Preenchido pelo middleware ResolveTenant nas rotas da empresa e pelos jobs que
 * restauram o contexto. Sem empresa definida (console, rotas públicas), o escopo
 * de tenant não filtra nada.
 */
class TenantContext
{
    private ?Company $company = null;

    public function set(Company $company): void
    {
        $this->company = $company;
    }

    public function forget(): void
    {
        $this->company = null;
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
