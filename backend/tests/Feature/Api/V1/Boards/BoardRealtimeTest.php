<?php

use App\Enums\SystemRole;
use App\Events\Boards\BoardElementsChanged;
use App\Events\Boards\BoardVersionSaved;
use App\Models\Board;
use App\Models\BoardElement;
use App\Models\BoardVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * Os testes usam o broadcaster "null", que não confere nada. Para testar a autorização dos
 * canais de verdade, troca para o Reverb (só assina a resposta, sem abrir conexão) e registra
 * os canais de novo nele.
 */
function useReverbBroadcaster(): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test',
    ]);

    require base_path('routes/channels.php');
}

it('autoriza o canal de presença só para quem é da empresa e pode ver boards', function () {
    useReverbBroadcaster();
    [$owner, $company] = ownerWithCompany();
    $board = Board::factory()->for($company)->create();
    $channel = ['socket_id' => '1234.5678', 'channel_name' => "presence-board.{$board->ulid}"];

    $auth = fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/broadcasting/auth', $channel)
        ->assertOk()
        ->json();

    expect($auth['auth'])->toStartWith('test-key:')
        ->and(json_decode($auth['channel_data'], true))->toMatchArray([
            'user_id' => $owner->id,
            'user_info' => ['id' => $owner->id, 'name' => $owner->name, 'can_edit' => true],
        ]);

    // Finanças não vê boards.
    $this->app['auth']->forgetGuards();
    $finance = memberOf($company, SystemRole::Finance);
    fromSpa($this)->actingAs($finance, 'web')->postJson('/api/v1/company/broadcasting/auth', $channel)->assertForbidden();

    // Dono de outra empresa: o board não existe para ele.
    [$stranger] = ownerWithCompany();
    $this->app['auth']->forgetGuards();
    fromSpa($this)->actingAs($stranger, 'web')->postJson('/api/v1/company/broadcasting/auth', $channel)->assertForbidden();

    // Sem login não chega nem a conferir o canal.
    $this->app['auth']->forgetGuards();
    fromSpa($this)->postJson('/api/v1/company/broadcasting/auth', $channel)->assertUnauthorized();
});

it('avisa quem está no board do lote salvo, menos quem salvou', function () {
    Event::fake([BoardElementsChanged::class]);
    [$owner, $company] = ownerWithCompany();
    $board = Board::factory()->for($company)->create();
    $note = boardElement(['props' => []]);
    $gone = BoardElement::factory()->for($board)->create(['company_id' => $company->id]);

    fromSpa($this)->actingAs($owner, 'web')
        ->withHeader('X-Socket-ID', '1234.5678')
        ->postJson("/api/v1/company/boards/{$board->ulid}/elements", ['upserts' => [$note], 'deletes' => [$gone->ulid]])
        ->assertOk();

    Event::assertDispatched(BoardElementsChanged::class, function (BoardElementsChanged $event) use ($board, $owner, $note, $gone) {
        $payload = $event->broadcastWith();

        return $event->broadcastOn()->name === "presence-board.{$board->ulid}"
            && $event->broadcastAs() === 'elements.changed'
            && $event->socket === '1234.5678'
            && $payload['reload'] === false
            && $payload['editor'] === ['id' => $owner->id, 'name' => $owner->name]
            && $payload['upserts'][0]['id'] === $note['id']
            && $payload['upserts'][0]['props'] == new stdClass
            && $payload['deletes'] === [$gone->ulid];
    });
});

it('lote grande demais para o WebSocket vira só um aviso para recarregar', function () {
    $elements = array_map(fn () => [...boardElement(), 'props' => ['text' => str_repeat('a', 500)]], range(1, 20));

    $payload = (new BoardElementsChanged('01board', ['id' => 1, 'name' => 'Ana'], $elements, []))->broadcastWith();

    expect($payload)->toBe(['editor' => ['id' => 1, 'name' => 'Ana'], 'reload' => true, 'upserts' => [], 'deletes' => []]);
});

it('guarda versão automática no primeiro autosave e no máximo uma a cada 10 minutos', function () {
    [$owner, $company] = ownerWithCompany();
    $board = Board::factory()->for($company)->create();
    $save = fn (array $element) => fromSpa($this)->actingAs($owner, 'web')
        ->postJson("/api/v1/company/boards/{$board->ulid}/elements", ['upserts' => [$element], 'deletes' => []])
        ->assertOk();

    $note = boardElement();
    $save($note);
    $save([...$note, 'x' => 500]);

    $versions = BoardVersion::query()->get();
    expect($versions)->toHaveCount(1)
        ->and($versions[0]->automatic)->toBeTrue()
        ->and($versions[0]->element_count)->toBe(1)
        ->and($versions[0]->company_id)->toBe($company->id);

    $this->travel(11)->minutes();
    $save([...$note, 'x' => 800]);
    expect(BoardVersion::query()->count())->toBe(2);

    // Mesmo conteúdo da última versão: não guarda outra igual.
    $this->travel(11)->minutes();
    $save([...$note, 'x' => 800]);
    expect(BoardVersion::query()->count())->toBe(2);
});

it('salva versão com nome pela fila, lista sem os elementos e abre com eles', function () {
    [$owner, $company] = ownerWithCompany();
    $board = Board::factory()->for($company)->create();
    BoardElement::factory()->for($board)->count(2)->create(['company_id' => $company->id]);

    Event::fake([BoardVersionSaved::class]);
    fromSpa($this)->actingAs($owner, 'web')
        ->postJson("/api/v1/company/boards/{$board->ulid}/versions", ['label' => '  Antes da reunião  '])
        ->assertAccepted();

    $id = fromSpa($this)->actingAs($owner, 'web')->getJson("/api/v1/company/boards/{$board->ulid}/versions")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.label', 'Antes da reunião')
        ->assertJsonPath('data.0.automatic', false)
        ->assertJsonPath('data.0.element_count', 2)
        ->assertJsonPath('data.0.author.name', $owner->name)
        ->assertJsonMissingPath('data.0.elements')
        ->json('data.0.id');

    // Quem está com o histórico aberto fica sabendo da versão nova.
    Event::assertDispatched(BoardVersionSaved::class, fn (BoardVersionSaved $event) => $event->broadcastOn()->name === "presence-board.{$board->ulid}"
        && $event->broadcastWith() === ['id' => $id]);

    fromSpa($this)->actingAs($owner, 'web')->getJson("/api/v1/company/boards/{$board->ulid}/versions/{$id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.elements')
        ->assertJsonStructure(['data' => ['elements' => [['id', 'type', 'x', 'y', 'width', 'height', 'rotation', 'z_index', 'props']]]]);

    fromSpa($this)->actingAs($owner, 'web')
        ->postJson("/api/v1/company/boards/{$board->ulid}/versions", ['label' => str_repeat('x', 81)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('label');
});

it('restaura uma versão, guardando antes o estado atual e avisando quem está no board', function () {
    [$owner, $company] = ownerWithCompany();
    $board = Board::factory()->for($company)->create();
    $note = boardElement(['props' => ['text' => 'Versão 1', 'fill' => '#FDE68A']]);
    $url = "/api/v1/company/boards/{$board->ulid}";

    fromSpa($this)->actingAs($owner, 'web')->postJson("{$url}/elements", ['upserts' => [$note], 'deletes' => []])->assertOk();
    $version = BoardVersion::query()->sole();

    $extra = boardElement(['type' => 'rect']);
    fromSpa($this)->actingAs($owner, 'web')->postJson("{$url}/elements", [
        'upserts' => [[...$note, 'props' => ['text' => 'Versão 2', 'fill' => '#FDE68A']], $extra],
        'deletes' => [],
    ])->assertOk();

    Event::fake([BoardElementsChanged::class]);

    fromSpa($this)->actingAs($owner, 'web')->postJson("{$url}/versions/{$version->ulid}/restore")
        ->assertOk()
        ->assertJsonCount(1, 'data.elements')
        ->assertJsonPath('data.elements.0.id', $note['id'])
        ->assertJsonPath('data.elements.0.props.text', 'Versão 1');

    $before = BoardVersion::query()->latest('id')->first();
    expect($before->label)->toStartWith('Antes de restaurar')
        ->and($before->automatic)->toBeFalse()
        ->and($before->element_count)->toBe(2);

    Event::assertDispatched(BoardElementsChanged::class, fn (BoardElementsChanged $event) => $event->broadcastWith()['reload'] === true);
});

it('não mostra nem restaura versões de outro board ou de outra empresa', function () {
    [$owner, $company] = ownerWithCompany();
    $board = Board::factory()->for($company)->create();
    $other = Board::factory()->for($company)->create();
    $version = BoardVersion::query()->create([
        'company_id' => $company->id, 'board_id' => $other->id, 'automatic' => false,
        'elements' => [], 'element_count' => 0, 'content_hash' => hash('sha256', '[]'),
    ]);

    fromSpa($this)->actingAs($owner, 'web')->getJson("/api/v1/company/boards/{$board->ulid}/versions/{$version->ulid}")->assertNotFound();
    fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/company/boards/{$board->ulid}/versions/{$version->ulid}/restore")->assertNotFound();

    [$stranger] = ownerWithCompany();
    $this->app['auth']->forgetGuards();
    fromSpa($this)->actingAs($stranger, 'web')->getJson("/api/v1/company/boards/{$other->ulid}/versions")->assertNotFound();
});

it('compartilha o board com link de leitura que funciona sem login e pode ser desligado', function () {
    [$owner, $company] = ownerWithCompany();
    $board = Board::factory()->for($company)->create(['title' => 'Mapa do site']);
    BoardElement::factory()->for($board)->create(['company_id' => $company->id]);
    $url = "/api/v1/company/boards/{$board->ulid}/share";

    $link = fromSpa($this)->actingAs($owner, 'web')->postJson($url)->assertOk()->json('data.share_url');
    expect($link)->toStartWith('http://localhost:5173/b/');

    // Ligar de novo mantém o mesmo link.
    fromSpa($this)->actingAs($owner, 'web')->postJson($url)->assertOk()->assertJsonPath('data.share_url', $link);

    $token = basename($link);
    $this->app['auth']->forgetGuards();

    $public = $this->getJson("/api/v1/public/boards/{$token}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Mapa do site')
        ->assertJsonPath('data.company.name', $company->name)
        ->assertJsonCount(1, 'data.elements')
        ->json('data');
    expect($public)->not->toHaveKeys(['id', 'share_url', 'project', 'creator']);

    fromSpa($this)->actingAs($owner, 'web')->deleteJson($url)->assertOk()->assertJsonPath('data.share_url', null);
    $this->app['auth']->forgetGuards();
    $this->getJson("/api/v1/public/boards/{$token}")->assertNotFound();
    $this->getJson('/api/v1/public/boards/'.str_repeat('a', 48))->assertNotFound();
});

it('só quem edita boards liga o link de leitura', function () {
    [, $company] = ownerWithCompany();
    $board = Board::factory()->for($company)->create();
    $finance = memberOf($company, SystemRole::Finance);

    fromSpa($this)->actingAs($finance, 'web')->postJson("/api/v1/company/boards/{$board->ulid}/share")->assertForbidden();

    [$stranger] = ownerWithCompany();
    $this->app['auth']->forgetGuards();
    fromSpa($this)->actingAs($stranger, 'web')->postJson("/api/v1/company/boards/{$board->ulid}/share")->assertNotFound();
    expect(Board::query()->withoutGlobalScopes()->find($board->id)->share_token)->toBeNull();
});
