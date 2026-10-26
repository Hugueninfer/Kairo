<?php

use App\DTOs\Roles\RoleData;
use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use App\Services\Companies\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('cria os papéis padrão junto com a empresa e dá ao criador o papel de dono', function () {
    $user = User::factory()->create();

    fromSpa($this)->actingAs($user, 'web')->postJson('/api/v1/companies', ['name' => 'Estúdio Lua'])
        ->assertCreated();

    fromSpa($this)->actingAs($user, 'web')->getJson('/api/v1/company/roles')
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.system_key', 'owner')
        ->assertJsonPath('data.0.is_locked', true)
        ->assertJsonPath('data.0.members_count', 1);

    fromSpa($this)->actingAs($user, 'web')->getJson('/api/v1/company')
        ->assertJsonPath('meta.role.name', 'Dono')
        ->assertJsonCount(count(Permission::cases()), 'meta.permissions');
});

it('lista o catálogo de permissões agrupado', function () {
    [$owner] = ownerWithCompany();

    fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/permissions')
        ->assertOk()
        ->assertJsonPath('data.0.group', 'Empresa')
        ->assertJsonPath('data.0.permissions.0.key', 'company.update');
});

it('cria um papel com as permissões escolhidas', function () {
    [$owner] = ownerWithCompany();

    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/roles', [
        'name' => 'Designer',
        'permissions' => ['boards.view', 'boards.manage', 'projects.view'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Designer')
        ->assertJsonPath('data.is_system', false)
        ->assertJsonPath('data.permissions', ['boards.manage', 'boards.view', 'projects.view'])
        ->assertJsonPath('data.members_count', 0);
});

it('recusa nome repetido e permissão inexistente', function () {
    [$owner] = ownerWithCompany();

    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/roles', ['name' => 'membro', 'permissions' => []])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'role_name_taken');

    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/roles', ['name' => 'X', 'permissions' => ['tudo']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('permissions.0');
});

it('edita as permissões de um papel padrão sem trocar o nome', function () {
    [$owner, $company] = ownerWithCompany();
    $member = roleOf($company, SystemRole::Member);

    fromSpa($this)->actingAs($owner, 'web')->putJson("/api/v1/company/roles/{$member->id}", [
        'name' => 'Outro nome',
        'permissions' => ['members.view', 'members.invite'],
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Membro')
        ->assertJsonPath('data.permissions', ['members.invite', 'members.view']);
});

it('não deixa alterar o papel de dono nem apagar papéis padrão', function () {
    [$owner, $company] = ownerWithCompany();

    fromSpa($this)->actingAs($owner, 'web')
        ->putJson('/api/v1/company/roles/'.roleOf($company, SystemRole::Owner)->id, ['name' => 'Dono', 'permissions' => []])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'owner_role_locked');

    fromSpa($this)->actingAs($owner, 'web')
        ->deleteJson('/api/v1/company/roles/'.roleOf($company, SystemRole::Finance)->id)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'system_role_locked');
});

it('só apaga um papel sem membros', function () {
    [$owner, $company] = ownerWithCompany();
    $role = app(RoleService::class)
        ->create($company, new RoleData('Estagiário', [Permission::ProjectsView]));
    $intern = memberOf($company, $role);

    fromSpa($this)->actingAs($owner, 'web')->deleteJson("/api/v1/company/roles/{$role->id}")
        ->assertConflict()
        ->assertJsonPath('code', 'role_in_use');

    fromSpa($this)->actingAs($owner, 'web')
        ->putJson("/api/v1/company/members/{$intern->id}/role", ['role_id' => roleOf($company, SystemRole::Member)->id])
        ->assertNoContent();

    fromSpa($this)->actingAs($owner, 'web')->deleteJson("/api/v1/company/roles/{$role->id}")
        ->assertNoContent();

    expect(Role::query()->whereKey($role->id)->exists())->toBeFalse();
});

it('troca o papel de um membro, mas nunca para dono nem o do dono', function () {
    [$owner, $company] = ownerWithCompany();
    $member = memberOf($company);
    $finance = roleOf($company, SystemRole::Finance);

    fromSpa($this)->actingAs($owner, 'web')
        ->putJson("/api/v1/company/members/{$member->id}/role", ['role_id' => $finance->id])
        ->assertNoContent();

    fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/members')
        ->assertJsonFragment(['id' => $member->id, 'role' => ['id' => $finance->id, 'name' => 'Financeiro']]);

    fromSpa($this)->actingAs($owner, 'web')
        ->putJson("/api/v1/company/members/{$member->id}/role", ['role_id' => roleOf($company, SystemRole::Owner)->id])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'owner_role_reserved');

    fromSpa($this)->actingAs($owner, 'web')
        ->putJson("/api/v1/company/members/{$owner->id}/role", ['role_id' => $finance->id])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'owner_role_reserved');
});

it('quem aceita o convite entra com o papel escolhido', function () {
    [$owner, $company] = ownerWithCompany();
    $finance = roleOf($company, SystemRole::Finance);

    fromSpa($this)->actingAs($owner, 'web')
        ->postJson('/api/v1/company/invitations', ['email' => 'bruno@kairo.test', 'role_id' => $finance->id])
        ->assertCreated()
        ->assertJsonPath('data.role.name', 'Financeiro');

    $invitation = Invitation::query()->latest('id')->firstOrFail();
    $invitation->forceFill(['token_hash' => hash('sha256', 'token-bruno')])->save();
    $bruno = User::factory()->create(['email' => 'bruno@kairo.test']);

    $this->app['auth']->forgetGuards();
    fromSpa($this)->actingAs($bruno, 'web')->postJson('/api/v1/invitations/token-bruno/accept')->assertOk();

    fromSpa($this)->actingAs($bruno, 'web')->getJson('/api/v1/company')
        ->assertJsonPath('meta.role.name', 'Financeiro')
        ->assertJsonPath('meta.permissions', SystemRole::Finance->defaultPermissionNames());
});

it('não convida com o papel de dono nem com papel de outra empresa', function () {
    [$owner, $company] = ownerWithCompany();
    [, $other] = ownerWithCompany();

    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/invitations', [
        'email' => 'bruno@kairo.test',
        'role_id' => roleOf($company, SystemRole::Owner)->id,
    ])->assertUnprocessable()->assertJsonPath('code', 'owner_role_reserved');

    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/invitations', [
        'email' => 'bruno@kairo.test',
        'role_id' => roleOf($other, SystemRole::Member)->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('role_id');
});

it('papéis de outra empresa não existem para quem está na empresa ativa', function () {
    [$owner] = ownerWithCompany();
    [, $other] = ownerWithCompany();

    fromSpa($this)->actingAs($owner, 'web')
        ->putJson('/api/v1/company/roles/'.roleOf($other, SystemRole::Member)->id, ['name' => 'X', 'permissions' => []])
        ->assertNotFound();
});

it('remover o membro também tira o papel dele na empresa', function () {
    [$owner, $company] = ownerWithCompany();
    $member = memberOf($company);

    fromSpa($this)->actingAs($owner, 'web')->deleteJson("/api/v1/company/members/{$member->id}")->assertNoContent();

    expect(roleOf($company, SystemRole::Member)->members()->whereKey($member->id)->exists())->toBeFalse();
});
