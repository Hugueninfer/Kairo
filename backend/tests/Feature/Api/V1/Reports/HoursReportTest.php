<?php

use App\Enums\SystemRole;
use App\Jobs\BuildHoursReport;
use App\Models\Client;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Services\Reports\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function reportEntries($company, $owner, $member): void
{
    $padaria = Client::factory()->create(['company_id' => $company->id, 'name' => 'Padaria']);
    $site = Project::factory()->for($company)->create(['name' => 'Site', 'client_id' => $padaria->id]);
    $interno = Project::factory()->for($company)->create(['name' => 'Interno']);
    $at = fn (string $day) => ['started_at' => "{$day} 12:00", 'ended_at' => "{$day} 14:00"];

    TimeEntry::factory()->create(['project_id' => $site->id, 'user_id' => $owner->id, 'duration_minutes' => 120, 'hourly_rate' => '150.00', ...$at('2026-10-05')]);
    TimeEntry::factory()->create(['project_id' => $site->id, 'user_id' => $member->id, 'duration_minutes' => 90, 'hourly_rate' => '100.00', ...$at('2026-10-06')]);
    TimeEntry::factory()->create(['project_id' => $interno->id, 'user_id' => $member->id, 'duration_minutes' => 60, 'billable' => false, 'hourly_rate' => null, ...$at('2026-10-07')]);
    // Fora do período.
    TimeEntry::factory()->create(['project_id' => $site->id, 'user_id' => $owner->id, 'duration_minutes' => 600, ...$at('2026-11-02')]);
}

it('agrupa as horas do período por projeto, pessoa e cliente, com valor', function () {
    [$owner, $company] = ownerWithCompany();
    $member = memberOf($company);
    $member->update(['name' => 'Ana']);
    reportEntries($company, $owner, $member);
    $url = '/api/v1/company/reports/hours?from=2026-10-01&to=2026-10-31';

    $report = fromSpa($this)->actingAs($owner, 'web')->getJson($url)->assertOk()->assertJsonPath('status', 'ready')->json('data');
    expect($report['rows'])->toBe([
        ['key' => Project::query()->where('name', 'Site')->value('ulid'), 'label' => 'Site', 'minutes' => 210, 'billable_minutes' => 210, 'amount' => '450.00'],
        ['key' => Project::query()->where('name', 'Interno')->value('ulid'), 'label' => 'Interno', 'minutes' => 60, 'billable_minutes' => 0, 'amount' => '0.00'],
    ])->and($report['totals'])->toBe(['minutes' => 270, 'billable_minutes' => 210, 'amount' => '450.00']);

    $byMember = fromSpa($this)->actingAs($owner, 'web')->getJson("{$url}&group=member")->assertOk()->json('data.rows');
    expect(array_column($byMember, 'label'))->toBe(['Ana', $owner->name])
        ->and(array_column($byMember, 'minutes'))->toBe([150, 120]);

    $byClient = fromSpa($this)->actingAs($owner, 'web')->getJson("{$url}&group=client")->assertOk()->json('data.rows');
    expect(array_column($byClient, 'label'))->toBe(['Padaria', 'Sem cliente']);
});

it('calcula na fila: responde 202 até ficar pronto e recalcula quando as horas mudam', function () {
    [$owner, $company] = ownerWithCompany();
    reportEntries($company, $owner, memberOf($company));
    $url = '/api/v1/company/reports/hours?from=2026-10-01&to=2026-10-31';

    Queue::fake();
    fromSpa($this)->actingAs($owner, 'web')->getJson($url)->assertStatus(202)->assertJson(['status' => 'processing', 'data' => null]);
    Queue::assertPushed(BuildHoursReport::class, 1);

    // O worker roda o job: o próximo pedido já vem do cache, sem outro job.
    Queue::pushed(BuildHoursReport::class)->first()->handle(app(ReportService::class));
    fromSpa($this)->actingAs($owner, 'web')->getJson($url)->assertOk()->assertJsonPath('data.totals.minutes', 270);
    Queue::assertPushed(BuildHoursReport::class, 1);

    // Uma hora nova muda a chave do cache.
    $this->travel(1)->seconds();
    TimeEntry::factory()->create(['project_id' => Project::query()->first()->id, 'duration_minutes' => 30, 'started_at' => '2026-10-20 12:00', 'ended_at' => '2026-10-20 12:30']);
    fromSpa($this)->actingAs($owner, 'web')->getJson($url)->assertStatus(202);
    Queue::assertPushed(BuildHoursReport::class, 2);
});

it('valida o período e exige a permissão de relatórios', function () {
    [$owner, $company] = ownerWithCompany();

    fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/reports/hours?from=2026-10-31&to=2026-10-01')
        ->assertUnprocessable()->assertJsonValidationErrors('to');
    fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/reports/hours?from=2025-01-01&to=2026-10-01')
        ->assertUnprocessable()->assertJsonValidationErrors('to');
    fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/reports/hours?from=2026-10-01&to=2026-10-31&group=x')
        ->assertUnprocessable()->assertJsonValidationErrors('group');

    $this->app['auth']->forgetGuards();
    fromSpa($this)->actingAs(memberOf($company), 'web')->getJson('/api/v1/company/reports/hours?from=2026-10-01&to=2026-10-31')->assertForbidden();
    $this->app['auth']->forgetGuards();
    fromSpa($this)->actingAs(memberOf($company, SystemRole::Finance), 'web')->getJson('/api/v1/company/reports/hours?from=2026-10-01&to=2026-10-31')->assertOk();
});

it('não conta horas de outra empresa', function () {
    [$owner] = ownerWithCompany();
    [$stranger, $other] = ownerWithCompany();
    TimeEntry::factory()->create(['project_id' => Project::factory()->for($other)->create()->id, 'user_id' => $stranger->id, 'started_at' => '2026-10-05 12:00']);

    fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/reports/hours?from=2026-10-01&to=2026-10-31')
        ->assertOk()
        ->assertJsonPath('data.rows', [])
        ->assertJsonPath('data.totals.minutes', 0);
});
