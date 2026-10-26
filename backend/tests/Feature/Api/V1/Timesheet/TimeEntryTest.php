<?php

use App\Enums\BillingType;
use App\Enums\ProjectStatus;
use App\Enums\SystemRole;
use App\Enums\TimeEntryStatus;
use App\Jobs\StopForgottenTimers;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Notifications\Timesheet\ForgottenTimerNotification;
use App\Services\Timesheet\TimeEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->travelTo(now()->setDate(2026, 10, 28)->setTime(15, 0)));

it('inicia e para o timer, congelando o valor/hora do projeto', function () {
    [$owner, $company] = ownerWithCompany();
    $project = Project::factory()->for($company)->create(['hourly_rate' => '150.00']);
    $task = Task::factory()->for($project)->create(['company_id' => $company->id]);

    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/time-entries/timer', [
        'project_id' => $project->ulid,
        'task_id' => $task->ulid,
        'description' => 'Layout',
    ])
        ->assertCreated()
        ->assertJsonPath('data.is_running', true)
        ->assertJsonPath('data.hourly_rate', '150.00')
        ->assertJsonPath('data.task.id', $task->ulid);

    fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/time-entries/timer')
        ->assertJsonPath('data.description', 'Layout');

    $this->travel(90)->minutes();

    fromSpa($this)->actingAs($owner, 'web')->deleteJson('/api/v1/company/time-entries/timer')
        ->assertOk()
        ->assertJsonPath('data.is_running', false)
        ->assertJsonPath('data.duration_minutes', 90)
        ->assertJsonPath('data.amount', '225.00');

    fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/time-entries/timer')->assertJsonPath('data', null);
    fromSpa($this)->actingAs($owner, 'web')->deleteJson('/api/v1/company/time-entries/timer')
        ->assertNotFound()
        ->assertJsonPath('code', 'timer_not_running');
});

it('iniciar outro timer para o que estava rodando', function () {
    [$owner, $company] = ownerWithCompany();
    $one = Project::factory()->for($company)->create();
    $two = Project::factory()->for($company)->create();

    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/time-entries/timer', ['project_id' => $one->ulid])->assertCreated();
    $this->travel(30)->minutes();
    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/time-entries/timer', ['project_id' => $two->ulid])->assertCreated();

    expect(TimeEntry::query()->whereNull('ended_at')->count())->toBe(1)
        ->and(TimeEntry::query()->where('project_id', $one->id)->value('duration_minutes'))->toBe(30);
});

it('usa o valor/hora da empresa e não cobra projeto de preço fechado', function () {
    [$owner, $company] = ownerWithCompany();
    $company->forceFill(['default_hourly_rate' => '80.00'])->save();
    $hourly = Project::factory()->for($company)->create();
    $fixed = Project::factory()->for($company)->create(['billing_type' => BillingType::Fixed]);

    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/time-entries', [
        'project_id' => $hourly->ulid, 'date' => '2026-10-27', 'minutes' => 120,
    ])->assertCreated()->assertJsonPath('data.hourly_rate', '80.00')->assertJsonPath('data.amount', '160.00');

    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/time-entries', [
        'project_id' => $fixed->ulid, 'date' => '2026-10-27', 'minutes' => 120,
    ])->assertCreated()->assertJsonPath('data.billable', false)->assertJsonPath('data.amount', null);
});

it('lança horas manualmente no fuso da empresa', function () {
    [$owner, $company] = ownerWithCompany();
    $project = Project::factory()->for($company)->create();

    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/time-entries', [
        'project_id' => $project->ulid,
        'date' => '2026-10-27',
        'start' => '14:00',
        'minutes' => 45,
    ])
        ->assertCreated()
        // 14:00 em São Paulo (UTC-3) = 17:00 UTC.
        ->assertJsonPath('data.started_at', '2026-10-27T17:00:00+00:00')
        ->assertJsonPath('data.ended_at', '2026-10-27T17:45:00+00:00')
        ->assertJsonPath('data.status.value', 'pending');
});

it('recusa tarefa de outro projeto e projeto arquivado', function () {
    [$owner, $company] = ownerWithCompany();
    $project = Project::factory()->for($company)->create();
    $otherTask = Task::factory()->for(Project::factory()->for($company))->create(['company_id' => $company->id]);
    $archived = Project::factory()->for($company)->status(ProjectStatus::Archived)->create();

    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/time-entries', [
        'project_id' => $project->ulid, 'task_id' => $otherTask->ulid, 'date' => '2026-10-27', 'minutes' => 30,
    ])->assertUnprocessable()->assertJsonPath('code', 'task_not_in_project');

    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/time-entries/timer', ['project_id' => $archived->ulid])
        ->assertUnprocessable()->assertJsonPath('code', 'project_closed');
});

it('só quem lançou edita, e aprovado fica travado', function () {
    [$owner, $company] = ownerWithCompany();
    $member = memberOf($company);
    $project = Project::factory()->for($company)->create();
    $entry = TimeEntry::factory()->for($project)->create(['user_id' => $member->id, 'company_id' => $company->id]);
    $payload = ['project_id' => $project->ulid, 'date' => '2026-10-27', 'minutes' => 30];

    fromSpa($this)->actingAs($owner, 'web')->putJson("/api/v1/company/time-entries/{$entry->ulid}", $payload)
        ->assertForbidden()->assertJsonPath('code', 'not_entry_owner');

    $this->app['auth']->forgetGuards();
    fromSpa($this)->actingAs($member, 'web')->putJson("/api/v1/company/time-entries/{$entry->ulid}", $payload)
        ->assertOk()->assertJsonPath('data.duration_minutes', 30);

    $entry->forceFill(['status' => TimeEntryStatus::Approved])->save();

    fromSpa($this)->actingAs($member, 'web')->deleteJson("/api/v1/company/time-entries/{$entry->ulid}")
        ->assertUnprocessable()->assertJsonPath('code', 'time_entry_locked');
});

it('aprova e recusa em lote, só com permissão', function () {
    [$owner, $company] = ownerWithCompany();
    $member = memberOf($company);
    $project = Project::factory()->for($company)->create();
    $entries = TimeEntry::factory()->for($project)->count(2)->create(['user_id' => $member->id, 'company_id' => $company->id]);
    $running = TimeEntry::factory()->for($project)->running()->create(['user_id' => $owner->id, 'company_id' => $company->id]);
    $ids = [...$entries->pluck('ulid')->all(), $running->ulid];

    fromSpa($this)->actingAs($member, 'web')->postJson('/api/v1/company/time-entries/review', ['ids' => $ids, 'status' => 'approved'])
        ->assertForbidden();

    $this->app['auth']->forgetGuards();
    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/time-entries/review', ['ids' => $ids, 'status' => 'approved'])
        ->assertOk()
        ->assertJsonPath('data.updated', 2);

    expect($entries->first()->fresh()->status)->toBe(TimeEntryStatus::Approved)
        ->and($entries->first()->fresh()->reviewed_at)->not->toBeNull()
        ->and($running->fresh()->status)->toBe(TimeEntryStatus::Pending);
});

it('sem permissão, cada um vê só as próprias horas', function () {
    [$owner, $company] = ownerWithCompany();
    $member = memberOf($company, SystemRole::Member);
    $project = Project::factory()->for($company)->create();
    TimeEntry::factory()->for($project)->create(['user_id' => $member->id, 'company_id' => $company->id]);
    TimeEntry::factory()->for($project)->create(['user_id' => $owner->id, 'company_id' => $company->id]);
    $range = 'from=2026-10-20&to=2026-10-31';

    fromSpa($this)->actingAs($member, 'web')->getJson("/api/v1/company/time-entries?{$range}")->assertJsonCount(1, 'data');
    fromSpa($this)->actingAs($member, 'web')->getJson("/api/v1/company/time-entries?{$range}&all=1")->assertJsonCount(1, 'data');
    fromSpa($this)->actingAs($member, 'web')->getJson("/api/v1/company/time-entries?{$range}&user={$owner->id}")->assertForbidden();

    $this->app['auth']->forgetGuards();
    fromSpa($this)->actingAs($owner, 'web')->getJson("/api/v1/company/time-entries?{$range}&all=1")->assertJsonCount(2, 'data');
});

it('monta a planilha da semana por projeto e tarefa', function () {
    [$owner, $company] = ownerWithCompany();
    $site = Project::factory()->for($company)->create(['name' => 'Site', 'hourly_rate' => '100.00']);
    $task = Task::factory()->for($site)->create(['title' => 'Layout', 'company_id' => $company->id]);
    $base = ['user_id' => $owner->id, 'company_id' => $company->id];

    // Segunda (26/10) 10:00 local, 2h na tarefa; quarta (28/10) 1h sem tarefa.
    TimeEntry::factory()->for($site)->create([...$base, 'task_id' => $task->id, 'started_at' => '2026-10-26 13:00:00', 'ended_at' => '2026-10-26 15:00:00', 'duration_minutes' => 120]);
    TimeEntry::factory()->for($site)->create([...$base, 'started_at' => '2026-10-28 12:00:00', 'ended_at' => '2026-10-28 13:00:00', 'duration_minutes' => 60]);
    // Domingo 01/11 às 01:00 UTC ainda é sábado 31/10 em São Paulo.
    TimeEntry::factory()->for($site)->create([...$base, 'started_at' => '2026-11-01 01:00:00', 'ended_at' => '2026-11-01 01:30:00', 'duration_minutes' => 30]);
    // Semana seguinte: fora.
    TimeEntry::factory()->for($site)->create([...$base, 'started_at' => '2026-11-03 12:00:00', 'ended_at' => '2026-11-03 13:00:00', 'duration_minutes' => 60]);

    fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/time-entries/week?week=2026-10-29')
        ->assertOk()
        ->assertJsonPath('data.week_start', '2026-10-26')
        ->assertJsonPath('data.days.6', '2026-11-01')
        ->assertJsonCount(2, 'data.rows')
        ->assertJsonPath('data.rows.0.task', null)
        ->assertJsonPath('data.rows.0.minutes', [0, 0, 60, 0, 0, 30, 0])
        ->assertJsonPath('data.rows.1.task.title', 'Layout')
        ->assertJsonPath('data.rows.1.minutes', [120, 0, 0, 0, 0, 0, 0])
        ->assertJsonPath('data.totals.total', 210)
        ->assertJsonPath('data.totals.amount', '350.00');
});

it('exporta CSV com separador ponto e vírgula', function () {
    [$owner, $company] = ownerWithCompany();
    $project = Project::factory()->for($company)->create(['name' => 'Site']);
    TimeEntry::factory()->for($project)->create(['user_id' => $owner->id, 'company_id' => $company->id, 'started_at' => '2026-10-27 12:00:00', 'ended_at' => '2026-10-27 13:30:00', 'duration_minutes' => 90]);

    $response = fromSpa($this)->actingAs($owner, 'web')->get('/api/v1/company/time-entries/export?from=2026-10-26&to=2026-11-01')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();

    expect($csv)->toContain('Data;Início;Fim;Pessoa;Projeto')
        ->and($csv)->toContain('27/10/2026;09:00;10:30;')
        ->and($csv)->toContain(';Site;;;1,50;Sim;100,00;150,00;');
});

it('o job para timers esquecidos no limite e avisa por e-mail', function () {
    Notification::fake();
    [$owner, $company] = ownerWithCompany();
    $project = Project::factory()->for($company)->create(['name' => 'Site']);
    $forgotten = TimeEntry::factory()->for($project)->create(['user_id' => $owner->id, 'company_id' => $company->id, 'started_at' => now()->subHours(12), 'ended_at' => null, 'duration_minutes' => 0]);
    $recent = TimeEntry::factory()->for($project)->running()->create(['user_id' => memberOf($company)->id, 'company_id' => $company->id]);

    (new StopForgottenTimers)->handle(app(TimeEntryService::class));

    expect($forgotten->fresh()->duration_minutes)->toBe(600)
        ->and($forgotten->fresh()->ended_at->equalTo($forgotten->started_at->copy()->addHours(10)))->toBeTrue()
        ->and($recent->fresh()->ended_at)->toBeNull();

    Notification::assertSentTo($owner, ForgottenTimerNotification::class, fn ($n) => $n->projectName === 'Site' && $n->hours === 10);
});

it('apontamento de outra empresa não existe', function () {
    [$owner] = ownerWithCompany();
    $foreign = TimeEntry::factory()->create(['user_id' => $owner->id]);

    fromSpa($this)->actingAs($owner, 'web')->deleteJson("/api/v1/company/time-entries/{$foreign->ulid}")->assertNotFound();
});
