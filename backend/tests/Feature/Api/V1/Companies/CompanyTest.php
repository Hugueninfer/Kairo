<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('cria a empresa com o usuário como dono, membro e empresa ativa', function () {
    $user = User::factory()->create();

    $response = fromSpa($this)->actingAs($user, 'web')->postJson('/api/v1/companies', [
        'name' => 'Estúdio Ana',
        'default_hourly_rate' => 150,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Estúdio Ana')
        ->assertJsonPath('data.default_hourly_rate', '150.00')
        ->assertJsonPath('data.is_owner', true)
        ->assertJsonPath('data.is_current', true);

    $company = Company::query()->where('ulid', $response->json('data.id'))->sole();

    expect($company->owner_id)->toBe($user->id)
        ->and($company->members()->whereKey($user->id)->exists())->toBeTrue()
        ->and($user->fresh()->current_company_id)->toBe($company->id);
});

it('expõe o ULID e nunca o id numérico da empresa', function () {
    [$owner, $company] = ownerWithCompany();

    fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company')
        ->assertOk()
        ->assertJsonPath('data.id', $company->ulid);
});

it('lista só as empresas das quais o usuário faz parte', function () {
    [$owner, $company] = ownerWithCompany();
    Company::factory()->create(['name' => 'Empresa de outra pessoa']);

    fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/companies')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $company->ulid);
});

it('troca a empresa ativa só para empresas das quais é membro', function () {
    [$owner] = ownerWithCompany();
    $other = Company::factory()->for($owner, 'owner')->create();
    $foreign = Company::factory()->create();

    fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/companies/{$other->ulid}/switch")
        ->assertOk()
        ->assertJsonPath('data.is_current', true);

    expect($owner->fresh()->current_company_id)->toBe($other->id);

    fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/companies/{$foreign->ulid}/switch")
        ->assertForbidden()
        ->assertJsonPath('code', 'not_company_member');
});

it('pede para escolher uma empresa quando não há empresa ativa', function () {
    $user = User::factory()->create();

    fromSpa($this)->actingAs($user, 'web')->getJson('/api/v1/company')
        ->assertStatus(409)
        ->assertJsonPath('code', 'no_active_company');
});

it('só o dono edita os dados da empresa', function () {
    [$owner, $company] = ownerWithCompany();
    $member = memberOf($company);

    fromSpa($this)->actingAs($member, 'web')->patchJson('/api/v1/company', ['name' => 'Hackeada'])
        ->assertForbidden();

    // O guard do Sanctum guarda o usuário entre requisições do mesmo teste.
    $this->app['auth']->forgetGuards();

    fromSpa($this)->actingAs($owner, 'web')->patchJson('/api/v1/company', ['name' => 'Novo nome', 'currency' => 'USD'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Novo nome')
        ->assertJsonPath('data.currency', 'USD');
});

it('ignora company_id e owner_id enviados pelo front', function () {
    $user = User::factory()->create();
    $victim = User::factory()->create();

    $response = fromSpa($this)->actingAs($user, 'web')->postJson('/api/v1/companies', [
        'name' => 'Teste',
        'owner_id' => $victim->id,
    ]);

    expect(Company::query()->where('ulid', $response->json('data.id'))->value('owner_id'))->toBe($user->id);
});
