<?php

use App\Models\Board;
use App\Models\BoardElement;
use App\Models\Company;
use App\Models\Project;
use App\Services\Boards\BoardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function boardElement(array $overrides = []): array
{
    return [
        'id' => strtolower((string) Str::ulid()),
        'type' => 'note',
        'x' => 100.5,
        'y' => -40,
        'width' => 200,
        'height' => 160,
        'rotation' => 0,
        'z_index' => 1,
        'props' => ['text' => 'Ideia: área de encomendas', 'fill' => '#FDE68A'],
        ...$overrides,
    ];
}

it('cria board ligado a um projeto e lista os mais recentes', function () {
    [$owner, $company] = ownerWithCompany();
    $project = Project::factory()->for($company)->create(['name' => 'Site da padaria']);
    Board::factory()->for($company)->create(['title' => 'Antigo', 'updated_at' => now()->subDay()]);

    $id = fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/boards', [
        'title' => 'Mapa do site', 'project_id' => $project->ulid,
    ])
        ->assertCreated()
        ->assertJsonPath('data.project.name', 'Site da padaria')
        ->assertJsonPath('data.elements', [])
        ->json('data.id');

    fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/boards')
        ->assertOk()
        ->assertJsonPath('data.0.id', $id)
        ->assertJsonPath('data.1.title', 'Antigo');

    fromSpa($this)->actingAs($owner, 'web')->getJson("/api/v1/company/boards?project={$project->ulid}")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('grava o lote do autosave: cria, atualiza e apaga, sem duplicar ao reenviar', function () {
    [$owner, $company] = ownerWithCompany();
    $board = Board::factory()->for($company)->create();
    $note = boardElement();
    $rect = boardElement(['type' => 'rect', 'z_index' => 2, 'props' => ['fill' => '#BFDBFE', 'stroke' => '#1E3A8A']]);
    $arrow = boardElement([
        'type' => 'arrow', 'x' => 0, 'y' => 0, 'width' => 0, 'height' => 0, 'z_index' => 3,
        'props' => ['points' => [0, 0, 120, 80], 'from' => $note['id'], 'to' => $rect['id'], 'stroke' => '#334155', 'hack' => 'ignorado'],
    ]);

    $payload = ['upserts' => [$note, $rect, $arrow], 'deletes' => []];
    fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/company/boards/{$board->ulid}/elements", $payload)->assertOk();
    fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/company/boards/{$board->ulid}/elements", $payload)->assertOk();

    expect(BoardElement::query()->count())->toBe(3);

    fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/company/boards/{$board->ulid}/elements", [
        'upserts' => [[...$note, 'x' => 300, 'props' => ['text' => 'Ideia revisada', 'fill' => '#FDE68A']]],
        'deletes' => [$rect['id']],
    ])->assertOk()->assertJsonStructure(['data' => ['updated_at']]);

    fromSpa($this)->actingAs($owner, 'web')->getJson("/api/v1/company/boards/{$board->ulid}")
        ->assertOk()
        ->assertJsonCount(2, 'data.elements')
        ->assertJsonPath('data.elements.0.id', $note['id'])
        ->assertJsonPath('data.elements.0.x', 300)
        ->assertJsonPath('data.elements.0.props.text', 'Ideia revisada')
        ->assertJsonPath('data.elements.1.type', 'arrow')
        ->assertJsonPath('data.elements.1.props.points', [0, 0, 120, 80])
        ->assertJsonPath('data.elements.1.props.to', $rect['id'])
        ->assertJsonMissingPath('data.elements.1.props.hack');

    expect(BoardElement::query()->first()->updated_by)->toBe($owner->id);
});

it('valida os elementos do lote', function () {
    [$owner, $company] = ownerWithCompany();
    $board = Board::factory()->for($company)->create();

    fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/company/boards/{$board->ulid}/elements", [
        'upserts' => [boardElement(['id' => 'nao-e-ulid', 'type' => 'video', 'props' => ['fill' => 'red']])],
        'deletes' => [],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['upserts.0.id', 'upserts.0.type', 'upserts.0.props.fill']);
});

it('respeita o limite de elementos do board', function () {
    [$owner, $company] = ownerWithCompany();
    $board = Board::factory()->for($company)->create();
    $rows = collect(range(1, BoardService::MAX_ELEMENTS))->map(fn (int $i) => [
        'ulid' => strtolower((string) Str::ulid()), 'company_id' => $company->id, 'board_id' => $board->id, 'type' => 'note',
        'x' => $i, 'y' => 0, 'props' => '{}', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $rows->chunk(500)->each(fn ($chunk) => BoardElement::query()->insert($chunk->values()->all()));

    fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/company/boards/{$board->ulid}/elements", [
        'upserts' => [boardElement()], 'deletes' => [],
    ])->assertUnprocessable()->assertJsonPath('code', 'board_too_large');

    // Trocar um elemento por outro continua cabendo.
    fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/company/boards/{$board->ulid}/elements", [
        'upserts' => [boardElement()], 'deletes' => [$rows->first()['ulid']],
    ])->assertOk();
});

it('não mostra nem grava em board de outra empresa', function () {
    [$owner] = ownerWithCompany();
    $other = Board::factory()->for(Company::factory())->create();

    fromSpa($this)->actingAs($owner, 'web')->getJson("/api/v1/company/boards/{$other->ulid}")->assertNotFound();
    fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/company/boards/{$other->ulid}/elements", [
        'upserts' => [boardElement()], 'deletes' => [],
    ])->assertNotFound();

    expect(BoardElement::query()->count())->toBe(0);
});

it('membro edita boards; renomeia e apaga com os elementos', function () {
    [$owner, $company] = ownerWithCompany();
    $board = Board::factory()->for($company)->create();

    fromSpa($this)->actingAs(memberOf($company), 'web')->postJson("/api/v1/company/boards/{$board->ulid}/elements", [
        'upserts' => [boardElement()], 'deletes' => [],
    ])->assertOk();

    fromSpa($this)->actingAs($owner, 'web')->putJson("/api/v1/company/boards/{$board->ulid}", ['title' => 'Novo nome'])
        ->assertOk()->assertJsonPath('data.title', 'Novo nome')->assertJsonPath('data.project', null);

    fromSpa($this)->actingAs($owner, 'web')->deleteJson("/api/v1/company/boards/{$board->ulid}")->assertNoContent();
    expect(BoardElement::query()->count())->toBe(0);
});
