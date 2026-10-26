<?php

use App\DTOs\Roles\RoleData;
use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Services\Companies\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('o membro comum vê a equipe, mas não convida, não remove nem edita a empresa', function () {
    [$owner, $company] = ownerWithCompany();
    $member = memberOf($company);

    fromSpa($this)->actingAs($member, 'web')->getJson('/api/v1/company/members')->assertOk();
    fromSpa($this)->actingAs($member, 'web')->postJson('/api/v1/company/invitations', ['email' => 'a@kairo.test'])->assertForbidden();
    fromSpa($this)->actingAs($member, 'web')->deleteJson("/api/v1/company/members/{$owner->id}")->assertForbidden();
    fromSpa($this)->actingAs($member, 'web')->patchJson('/api/v1/company', ['name' => 'Tomada'])
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');

    fromSpa($this)->actingAs($member, 'web')->getJson('/api/v1/company')
        ->assertJsonPath('meta.role.name', 'Membro')
        ->assertJsonPath('meta.permissions', SystemRole::Member->defaultPermissionNames());
});

it('o financeiro não gerencia papéis', function () {
    [, $company] = ownerWithCompany();
    $finance = memberOf($company, SystemRole::Finance);

    fromSpa($this)->actingAs($finance, 'web')->getJson('/api/v1/company/permissions')->assertForbidden();
    fromSpa($this)->actingAs($finance, 'web')->postJson('/api/v1/company/roles', ['name' => 'X', 'permissions' => []])->assertForbidden();
});

it('o administrador faz o que o dono faz na equipe', function () {
    [, $company] = ownerWithCompany();
    $admin = memberOf($company, SystemRole::Admin);
    $member = memberOf($company);

    fromSpa($this)->actingAs($admin, 'web')->postJson('/api/v1/company/invitations', ['email' => 'a@kairo.test'])->assertCreated();
    fromSpa($this)->actingAs($admin, 'web')->deleteJson("/api/v1/company/members/{$member->id}")->assertNoContent();
});

it('um papel personalizado libera exatamente o que foi marcado', function () {
    [, $company] = ownerWithCompany();
    $role = app(RoleService::class)->create($company, new RoleData('Recrutador', [Permission::MembersInvite]));
    $recruiter = memberOf($company, $role);

    fromSpa($this)->actingAs($recruiter, 'web')->postJson('/api/v1/company/invitations', ['email' => 'a@kairo.test'])->assertCreated();
    fromSpa($this)->actingAs($recruiter, 'web')->getJson('/api/v1/company/members')->assertForbidden();
});

it('a permissão vale só na empresa onde o papel foi dado', function () {
    [, $company] = ownerWithCompany();
    [, $other] = ownerWithCompany();
    $user = memberOf($company, SystemRole::Admin);

    // Na outra empresa ele é só membro.
    $other->members()->attach($user->id);
    app(RoleService::class)->assignOnJoin($user, $other, null);

    fromSpa($this)->actingAs($user, 'web')
        ->withHeader('X-Company-Id', $other->ulid)
        ->postJson('/api/v1/company/invitations', ['email' => 'a@kairo.test'])
        ->assertForbidden();

    $this->app['auth']->forgetGuards();

    fromSpa($this)->actingAs($user->fresh(), 'web')
        ->withHeader('X-Company-Id', $company->ulid)
        ->postJson('/api/v1/company/invitations', ['email' => 'a@kairo.test'])
        ->assertCreated();
});
