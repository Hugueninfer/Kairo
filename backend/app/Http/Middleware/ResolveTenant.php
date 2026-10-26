<?php

namespace App\Http\Middleware;

use App\Exceptions\Companies\NoActiveCompanyException;
use App\Exceptions\Companies\NotCompanyMemberException;
use App\Models\User;
use App\Repositories\Contracts\CompanyRepository;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define a empresa ativa da requisição.
 *
 * Usa o header X-Company-Id (ULID) quando enviado, senão a última empresa escolhida
 * pelo usuário. Sempre confere se o usuário é membro antes de liberar.
 */
class ResolveTenant
{
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly TenantContext $tenant,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        $ulid = $request->header('X-Company-Id');

        if (is_string($ulid) && $ulid !== '') {
            // Empresa inexistente e empresa alheia respondem igual, para não revelar quais existem.
            $company = $this->companies->findByUlid($ulid) ?? throw new NotCompanyMemberException;
        } else {
            $company = $user->currentCompany ?? throw new NoActiveCompanyException;
        }

        if (! $this->companies->isMember($company, $user)) {
            throw new NotCompanyMemberException;
        }

        $this->tenant->set($company);

        return $next($request);
    }
}
