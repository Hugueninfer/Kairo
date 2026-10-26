<?php

use App\Enums\ProjectStatus;
use App\Enums\SystemRole;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\Tasks\TaskAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/**
 * @return list<string> títulos da coluna, na ordem
 */
function column(Project $project, TaskStatus $status): array
{
    return Task::query()->where('project_id', $project->id)->where('status', $status)
        ->orderBy('position')->pluck('title')->all();
}

it('cria tarefa no fim da coluna e avisa quem recebeu, pela fila', function () {
    Notification::fake();
    [$owner, $company] = ownerWithCompany();
    $member = memberOf($company);
    $project = Project::factory()->for($company)->create();
    Task::factory()->for($project)->create(['title' => 'Primeira', 'company_id' => $company->id]);

    fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/company/projects/{$project->ulid}/tasks", [
        'title' => 'Criar layout',
        'priority' => 'high',
        'assignee_id' => $member->id,
        'due_on' => '2026-11-20',
    ])
        ->assertCreated()
        ->assertJsonPath('data.position', 1)
        ->assertJsonPath('data.priority.label', 'Alta')
        ->assertJsonPath('data.assignee', ['id' => $member->id, 'name' => $member->name]);

    Notification::assertSentTo($member, TaskAssignedNotification::class, function (TaskAssignedNotification $n) use ($project) {
        return $n->projectUlid === $project->ulid && $n->dueOn === '20/11/2026' && $n->queue === 'mail';
    });
});

it('não avisa quem atribui a tarefa a si mesmo', function () {
    Notification::fake();
    [$owner, $company] = ownerWithCompany();
    $project = Project::factory()->for($company)->create();

    fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/company/projects/{$project->ulid}/tasks", [
        'title' => 'Minha', 'assignee_id' => $owner->id,
    ])->assertCreated();

    Notification::assertNothingSent();
});

it('só atribui a membros da empresa', function () {
    [$owner, $company] = ownerWithCompany();
    $project = Project::factory()->for($company)->create();
    $stranger = User::factory()->create();

    fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/company/projects/{$project->ulid}/tasks", [
        'title' => 'X', 'assignee_id' => $stranger->id,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'assignee_not_member');
});

it('projeto arquivado não recebe tarefas', function () {
    [$owner, $company] = ownerWithCompany();
    $project = Project::factory()->for($company)->status(ProjectStatus::Archived)->create();

    fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/company/projects/{$project->ulid}/tasks", ['title' => 'X'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'project_closed');
});

it('move a tarefa entre colunas e reorganiza as posições', function () {
    [$owner, $company] = ownerWithCompany();
    $project = Project::factory()->for($company)->create();
    foreach (['A', 'B', 'C'] as $i => $title) {
        Task::factory()->for($project)->create(['title' => $title, 'position' => $i, 'company_id' => $company->id]);
    }
    Task::factory()->for($project)->create(['title' => 'X', 'status' => TaskStatus::Done, 'position' => 0, 'company_id' => $company->id]);
    $b = Task::query()->where('title', 'B')->firstOrFail();

    fromSpa($this)->actingAs($owner, 'web')
        ->putJson("/api/v1/company/projects/{$project->ulid}/tasks/{$b->ulid}/move", ['status' => 'done', 'position' => 0])
        ->assertOk()
        ->assertJsonPath('data.status.value', 'done')
        ->assertJsonPath('data.position', 0);

    expect(column($project, TaskStatus::Todo))->toBe(['A', 'C'])
        ->and(column($project, TaskStatus::Done))->toBe(['B', 'X'])
        ->and($b->fresh()->completed_at)->not->toBeNull();

    // Volta para "A fazer" no fim (posição maior que a coluna vira a última).
    fromSpa($this)->actingAs($owner, 'web')
        ->putJson("/api/v1/company/projects/{$project->ulid}/tasks/{$b->ulid}/move", ['status' => 'todo', 'position' => 99])
        ->assertJsonPath('data.position', 2);

    expect(column($project, TaskStatus::Todo))->toBe(['A', 'C', 'B'])
        ->and(column($project, TaskStatus::Done))->toBe(['X'])
        ->and($b->fresh()->completed_at)->toBeNull();
});

it('reordena dentro da mesma coluna', function () {
    [$owner, $company] = ownerWithCompany();
    $project = Project::factory()->for($company)->create();
    foreach (['A', 'B', 'C'] as $i => $title) {
        Task::factory()->for($project)->create(['title' => $title, 'position' => $i, 'company_id' => $company->id]);
    }
    $c = Task::query()->where('title', 'C')->firstOrFail();

    fromSpa($this)->actingAs($owner, 'web')
        ->putJson("/api/v1/company/projects/{$project->ulid}/tasks/{$c->ulid}/move", ['status' => 'todo', 'position' => 0])
        ->assertOk();

    expect(column($project, TaskStatus::Todo))->toBe(['C', 'A', 'B']);
});

it('apagar fecha o espaço na coluna', function () {
    [$owner, $company] = ownerWithCompany();
    $project = Project::factory()->for($company)->create();
    foreach (['A', 'B', 'C'] as $i => $title) {
        Task::factory()->for($project)->create(['title' => $title, 'position' => $i, 'company_id' => $company->id]);
    }
    $a = Task::query()->where('title', 'A')->firstOrFail();

    fromSpa($this)->actingAs($owner, 'web')->deleteJson("/api/v1/company/projects/{$project->ulid}/tasks/{$a->ulid}")->assertNoContent();

    expect(Task::query()->orderBy('position')->pluck('position', 'title')->all())->toBe(['B' => 0, 'C' => 1]);
});

it('a tarefa precisa ser do projeto da URL', function () {
    [$owner, $company] = ownerWithCompany();
    $one = Project::factory()->for($company)->create();
    $two = Project::factory()->for($company)->create();
    $task = Task::factory()->for($two)->create(['company_id' => $company->id]);

    fromSpa($this)->actingAs($owner, 'web')
        ->putJson("/api/v1/company/projects/{$one->ulid}/tasks/{$task->ulid}", ['title' => 'Y'])
        ->assertNotFound();
});

it('editar troca a coluna e avisa o novo responsável', function () {
    Notification::fake();
    [$owner, $company] = ownerWithCompany();
    $member = memberOf($company);
    $project = Project::factory()->for($company)->create();
    $task = Task::factory()->for($project)->create(['company_id' => $company->id]);

    fromSpa($this)->actingAs($owner, 'web')->putJson("/api/v1/company/projects/{$project->ulid}/tasks/{$task->ulid}", [
        'title' => 'Revisar textos',
        'status' => 'review',
        'assignee_id' => $member->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Revisar textos')
        ->assertJsonPath('data.status.value', 'review');

    Notification::assertSentToTimes($member, TaskAssignedNotification::class, 1);
});

it('minhas tarefas lista só as abertas do usuário na empresa ativa', function () {
    [, $company] = ownerWithCompany();
    $member = memberOf($company, SystemRole::Member);
    $project = Project::factory()->for($company)->create();
    Task::factory()->for($project)->create(['title' => 'Aberta', 'assignee_id' => $member->id, 'company_id' => $company->id]);
    Task::factory()->for($project)->create(['title' => 'Feita', 'assignee_id' => $member->id, 'status' => TaskStatus::Done, 'company_id' => $company->id]);
    Task::factory()->for($project)->create(['title' => 'De outro', 'company_id' => $company->id]);

    fromSpa($this)->actingAs($member, 'web')->getJson('/api/v1/company/tasks/mine')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Aberta')
        ->assertJsonPath('data.0.project.id', $project->ulid);
});

it('o membro move tarefas, mas o financeiro não', function () {
    [, $company] = ownerWithCompany();
    $member = memberOf($company);
    $finance = memberOf($company, SystemRole::Finance);
    $project = Project::factory()->for($company)->create();
    $task = Task::factory()->for($project)->create(['company_id' => $company->id]);
    $url = "/api/v1/company/projects/{$project->ulid}/tasks/{$task->ulid}/move";

    fromSpa($this)->actingAs($member, 'web')->putJson($url, ['status' => 'in_progress', 'position' => 0])->assertOk();
    $this->app['auth']->forgetGuards();
    fromSpa($this)->actingAs($finance, 'web')->putJson($url, ['status' => 'done', 'position' => 0])->assertForbidden();
});
