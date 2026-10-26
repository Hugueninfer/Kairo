<?php

use App\Enums\ProjectStatus;
use App\Enums\SystemRole;
use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('cria um projeto ligado a um cliente pelo ULID', function () {
    [$owner, $company] = ownerWithCompany();
    $client = Client::factory()->for($company)->create(['name' => 'Acme']);

    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/projects', [
        'name' => 'Site institucional',
        'client_id' => $client->ulid,
        'billing_type' => 'fixed',
        'budget' => '4500',
        'starts_on' => '2026-11-01',
        'due_on' => '2026-12-15',
    ])
        ->assertCreated()
        ->assertJsonPath('data.client', ['id' => $client->ulid, 'name' => 'Acme'])
        ->assertJsonPath('data.status.value', 'active')
        ->assertJsonPath('data.billing_type.label', 'Preço fechado')
        ->assertJsonPath('data.budget', '4500.00')
        ->assertJsonPath('data.open_tasks_count', 0);
});

it('recusa cliente de outra empresa e prazo antes do início', function () {
    [$owner] = ownerWithCompany();
    [, $other] = ownerWithCompany();
    $foreign = Client::factory()->for($other)->create();

    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/projects', [
        'name' => 'X',
        'client_id' => $foreign->ulid,
        'starts_on' => '2026-11-10',
        'due_on' => '2026-11-01',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['client_id', 'due_on']);
});

it('filtra por status e cliente e conta as tarefas', function () {
    [$owner, $company] = ownerWithCompany();
    $client = Client::factory()->for($company)->create();
    $site = Project::factory()->for($company)->create(['name' => 'Site', 'client_id' => $client->id]);
    Project::factory()->for($company)->status(ProjectStatus::Archived)->create(['name' => 'Antigo']);
    Task::factory()->for($site)->count(2)->create();
    Task::factory()->for($site)->create(['status' => TaskStatus::Done]);

    fromSpa($this)->actingAs($owner, 'web')->getJson("/api/v1/company/projects?status=active&client={$client->ulid}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.open_tasks_count', 2)
        ->assertJsonPath('data.0.done_tasks_count', 1);
});

it('projeto de outra empresa não existe', function () {
    [$owner] = ownerWithCompany();
    $foreign = Project::factory()->create();

    fromSpa($this)->actingAs($owner, 'web')->getJson("/api/v1/company/projects/{$foreign->ulid}")->assertNotFound();
    fromSpa($this)->actingAs($owner, 'web')->getJson("/api/v1/company/projects/{$foreign->ulid}/tasks")->assertNotFound();
});

it('o financeiro não cria projetos', function () {
    [, $company] = ownerWithCompany();
    $finance = memberOf($company, SystemRole::Finance);

    fromSpa($this)->actingAs($finance, 'web')->getJson('/api/v1/company/projects')->assertOk();
    fromSpa($this)->actingAs($finance, 'web')->postJson('/api/v1/company/projects', ['name' => 'X'])->assertForbidden();
});
