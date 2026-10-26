<?php

use App\Enums\SystemRole;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('cria, edita e apaga um cliente', function () {
    [$owner] = ownerWithCompany();

    $id = fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/clients', [
        'name' => '  Padaria Pão Quente ',
        'email' => 'contato@padaria.test',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Padaria Pão Quente')
        ->json('data.id');

    fromSpa($this)->actingAs($owner, 'web')->putJson("/api/v1/company/clients/{$id}", ['name' => 'Padaria Nova', 'phone' => '21 99999-0000'])
        ->assertOk()
        ->assertJsonPath('data.phone', '21 99999-0000')
        ->assertJsonPath('data.email', null);

    fromSpa($this)->actingAs($owner, 'web')->deleteJson("/api/v1/company/clients/{$id}")->assertNoContent();

    expect(Client::query()->where('ulid', $id)->exists())->toBeFalse()
        ->and(Client::withTrashed()->where('ulid', $id)->exists())->toBeTrue();
});

it('lista com busca, paginação e contagem de projetos', function () {
    [$owner, $company] = ownerWithCompany();
    $acme = Client::factory()->for($company)->create(['name' => 'Acme Ltda']);
    Client::factory()->for($company)->create(['name' => 'Beta & Cia']);
    Project::factory()->for($company)->create(['client_id' => $acme->id]);

    fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/clients?search=acm')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.projects_count', 1)
        ->assertJsonPath('meta.total', 1);

    // Curingas do LIKE são buscados como texto.
    fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/clients?search=%25')
        ->assertJsonCount(0, 'data');
});

it('não mostra clientes de outra empresa', function () {
    [$owner] = ownerWithCompany();
    [, $other] = ownerWithCompany();
    $foreign = Client::factory()->for($other)->create();

    fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/clients')->assertJsonCount(0, 'data');
    fromSpa($this)->actingAs($owner, 'web')->getJson("/api/v1/company/clients/{$foreign->ulid}")->assertNotFound();
});

it('o membro vê clientes, mas não cadastra', function () {
    [, $company] = ownerWithCompany();
    $member = memberOf($company, SystemRole::Member);

    fromSpa($this)->actingAs($member, 'web')->getJson('/api/v1/company/clients')->assertOk();
    fromSpa($this)->actingAs($member, 'web')->postJson('/api/v1/company/clients', ['name' => 'X'])->assertForbidden();
});
