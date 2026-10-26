<?php

use App\Models\Company;
use App\Models\Invitation;
use App\Repositories\Contracts\InvitationRepository;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Regra de ouro do multi-tenant: dados de uma empresa nunca aparecem para outra.

it('não lista convites de outra empresa', function () {
    [$ownerA, $companyA] = ownerWithCompany();
    [, $companyB] = ownerWithCompany();
    Invitation::factory()->for($companyA)->create(['email' => 'a@kairo.test']);
    Invitation::factory()->for($companyB)->create(['email' => 'b@kairo.test']);

    fromSpa($this)->actingAs($ownerA, 'web')->getJson('/api/v1/company/invitations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'a@kairo.test');
});

it('responde 404 ao tentar cancelar convite de outra empresa', function () {
    [$ownerA] = ownerWithCompany();
    [, $companyB] = ownerWithCompany();
    $invitationB = Invitation::factory()->for($companyB)->create();

    fromSpa($this)->actingAs($ownerA, 'web')->deleteJson("/api/v1/company/invitations/{$invitationB->ulid}")
        ->assertNotFound();

    expect($invitationB->fresh()->status->value)->toBe('pending');
});

it('recusa o header X-Company-Id de empresa da qual não é membro', function () {
    [$ownerA] = ownerWithCompany();
    [, $companyB] = ownerWithCompany();

    fromSpa($this)->actingAs($ownerA, 'web')
        ->withHeader('X-Company-Id', $companyB->ulid)
        ->getJson('/api/v1/company')
        ->assertForbidden()
        ->assertJsonPath('code', 'not_company_member');
});

it('responde igual para X-Company-Id inexistente', function () {
    [$ownerA] = ownerWithCompany();

    fromSpa($this)->actingAs($ownerA, 'web')
        ->withHeader('X-Company-Id', '01JZZZZZZZZZZZZZZZZZZZZZZZ')
        ->getJson('/api/v1/company')
        ->assertForbidden()
        ->assertJsonPath('code', 'not_company_member');
});

it('escopo global filtra e preenche company_id pela empresa ativa', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    Invitation::factory()->for($companyB)->create();

    app(TenantContext::class)->set($companyA);

    $created = Invitation::factory()->make(['company_id' => null, 'invited_by' => $companyA->owner_id]);
    $created->save();

    expect($created->company_id)->toBe($companyA->id)
        ->and(Invitation::query()->count())->toBe(1);
});

it('o repository aplica o CompanyCriteria mesmo sem o escopo global', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    Invitation::factory()->for($companyA)->create();
    Invitation::factory()->for($companyB)->create();

    app(TenantContext::class)->set($companyA);

    $repository = app(InvitationRepository::class);
    $repository->scopeQuery(fn ($query) => $query->withoutGlobalScope('company'));

    expect($repository->all())->toHaveCount(1);
});
