<?php

use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

/**
 * Simula uma requisição vinda do front (SPA), para o Sanctum usar sessão e cookies.
 */
function fromSpa(TestCase $test): TestCase
{
    return $test->withHeader('Referer', 'http://localhost:5173');
}

/**
 * Usuário logado (via SPA) com uma empresa ativa da qual ele é dono.
 *
 * @return array{0: User, 1: Company}
 */
function ownerWithCompany(): array
{
    $owner = User::factory()->create();
    $company = Company::factory()->for($owner, 'owner')->create();
    $owner->forceFill(['current_company_id' => $company->id])->save();

    return [$owner, $company];
}

/**
 * Adiciona um membro comum à empresa e já deixa a empresa como ativa para ele.
 */
function memberOf(Company $company): User
{
    $member = User::factory()->create();
    $company->members()->attach($member->id);
    $member->forceFill(['current_company_id' => $company->id])->save();

    return $member;
}
