<?php

use App\Enums\BillingType;
use App\Enums\QuoteStatus;
use App\Enums\SystemRole;
use App\Jobs\ExpireQuotes;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Project;
use App\Models\Quote;
use App\Notifications\Quotes\QuoteDecidedNotification;
use App\Notifications\Quotes\QuoteSentNotification;
use App\Services\Quotes\QuoteService;
use App\Support\PublicLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 27)->setTime(15, 0));
    Notification::fake();
    Storage::fake('local');
});

/**
 * @return array<string, mixed>
 */
function quotePayload(Client $client, array $overrides = []): array
{
    return [
        'client_id' => $client->ulid,
        'title' => 'Site institucional',
        'introduction' => 'Proposta para o novo site.',
        'terms' => '50% na entrada e 50% na entrega.',
        'valid_until' => '2026-11-10',
        'discount' => '100',
        'tax_rate' => '6',
        'items' => [
            ['description' => 'Layout', 'type' => 'fixed', 'quantity' => 1, 'unit_price' => '1500'],
            ['description' => 'Desenvolvimento', 'type' => 'hourly', 'quantity' => '20', 'unit_price' => '120.00'],
        ],
        ...$overrides,
    ];
}

/**
 * Caminho da API com a assinatura do link que vai no e-mail do cliente.
 */
function publicQuoteUrl(Quote $quote): string
{
    $link = app(PublicLinks::class)->quote($quote->refresh());

    return '/api/v1/public/quotes/'.$quote->ulid.'?'.parse_url($link, PHP_URL_QUERY);
}

function sentQuote(Company $company, array $attributes = []): Quote
{
    $quote = Quote::factory()->for($company)->status(QuoteStatus::Sent)->create($attributes);
    $quote->items()->create(['position' => 0, 'description' => 'Consultoria', 'type' => 'hourly', 'quantity' => '10', 'unit_price' => '100', 'total' => '1000']);

    return $quote;
}

it('cria orçamento com número sequencial e totais calculados no servidor', function () {
    [$owner, $company] = ownerWithCompany();
    $client = Client::factory()->for($company)->create();

    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/quotes', quotePayload($client))
        ->assertCreated()
        ->assertJsonPath('data.number', 'ORC-2026-0001')
        ->assertJsonPath('data.status.value', 'draft')
        ->assertJsonPath('data.subtotal', '3900.00')
        ->assertJsonPath('data.tax_amount', '228.00')
        ->assertJsonPath('data.total', '4028.00')
        ->assertJsonPath('data.items.1.total', '2400.00')
        ->assertJsonPath('data.items.1.type.unit', 'h');

    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/quotes', quotePayload($client))
        ->assertJsonPath('data.number', 'ORC-2026-0002');
});

it('valida itens, desconto e cliente de outra empresa', function () {
    [$owner, $company] = ownerWithCompany();
    $client = Client::factory()->for($company)->create();
    $foreign = Client::factory()->create();

    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/quotes', quotePayload($foreign, ['items' => []]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['client_id', 'items']);

    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/quotes', quotePayload($client, ['discount' => '99999']))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'discount_too_high');
});

it('o membro comum não vê orçamentos', function () {
    [, $company] = ownerWithCompany();

    fromSpa($this)->actingAs(memberOf($company), 'web')->getJson('/api/v1/company/quotes')->assertForbidden();
});

it('o financeiro cria orçamentos', function () {
    [, $company] = ownerWithCompany();
    $client = Client::factory()->for($company)->create();

    fromSpa($this)->actingAs(memberOf($company, SystemRole::Finance), 'web')->postJson('/api/v1/company/quotes', quotePayload($client))->assertCreated();
});

it('não mostra orçamento de outra empresa', function () {
    [$owner] = ownerWithCompany();
    $other = Quote::factory()->create();

    fromSpa($this)->actingAs($owner, 'web')->getJson("/api/v1/company/quotes/{$other->ulid}")->assertNotFound();
});

it('envia ao cliente com PDF anexo e link assinado', function () {
    [$owner, $company] = ownerWithCompany();
    $client = Client::factory()->for($company)->create(['email' => 'cliente@padaria.test']);
    $id = fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/quotes', quotePayload($client))->json('data.id');

    fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/company/quotes/{$id}/send")
        ->assertOk()
        ->assertJsonPath('data.status.value', 'sent');

    $quote = Quote::query()->where('ulid', $id)->firstOrFail();
    Storage::disk('local')->assertExists((string) $quote->pdf_path);

    Notification::assertSentOnDemand(
        QuoteSentNotification::class,
        function (QuoteSentNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($id) {
            return array_key_exists('cliente@padaria.test', $notifiable->routes['mail'])
                && str_contains($notification->url, "/orcamento/{$id}?expires=")
                && str_contains($notification->url, 'signature=')
                && $notification->total === "R\$\u{a0}4.028,00";
        },
    );
});

it('não envia sem e-mail do cliente nem com a validade vencida', function () {
    [$owner, $company] = ownerWithCompany();
    $noEmail = Quote::factory()->for($company)->create(['client_id' => Client::factory()->for($company)->create(['email' => null])->id]);
    $old = Quote::factory()->for($company)->create(['valid_until' => '2026-10-26']);

    fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/company/quotes/{$noEmail->ulid}/send")
        ->assertUnprocessable()->assertJsonPath('code', 'client_without_email');
    fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/company/quotes/{$old->ulid}/send")
        ->assertUnprocessable()->assertJsonPath('code', 'quote_expired');
});

it('editar um orçamento enviado volta para rascunho; aceito fica travado', function () {
    [$owner, $company] = ownerWithCompany();
    $sent = sentQuote($company);
    $accepted = Quote::factory()->for($company)->status(QuoteStatus::Accepted)->create();

    fromSpa($this)->actingAs($owner, 'web')->putJson("/api/v1/company/quotes/{$sent->ulid}", quotePayload($sent->client))
        ->assertOk()
        ->assertJsonPath('data.status.value', 'draft')
        ->assertJsonCount(2, 'data.items');

    fromSpa($this)->actingAs($owner, 'web')->putJson("/api/v1/company/quotes/{$accepted->ulid}", quotePayload($accepted->client))
        ->assertUnprocessable()->assertJsonPath('code', 'quote_locked');
    fromSpa($this)->actingAs($owner, 'web')->deleteJson("/api/v1/company/quotes/{$accepted->ulid}")
        ->assertUnprocessable()->assertJsonPath('code', 'quote_locked');
});

it('o cliente abre pelo link assinado e o orçamento fica visualizado', function () {
    [, $company] = ownerWithCompany();
    $quote = sentQuote($company);

    $this->getJson(publicQuoteUrl($quote))
        ->assertOk()
        ->assertJsonPath('data.number', $quote->number)
        ->assertJsonPath('data.awaits_decision', true)
        ->assertJsonPath('data.company.name', $company->name)
        ->assertJsonMissingPath('data.id');

    expect($quote->refresh()->status)->toBe(QuoteStatus::Viewed)->and($quote->viewed_at)->not->toBeNull();

    $this->getJson("/api/v1/public/quotes/{$quote->ulid}")->assertForbidden();
    $this->getJson(publicQuoteUrl($quote).'x')->assertForbidden();
});

it('rascunho não abre pelo link', function () {
    [, $company] = ownerWithCompany();
    $quote = Quote::factory()->for($company)->create();

    $this->getJson(publicQuoteUrl($quote))->assertNotFound();
});

it('aceite cria contrato e projeto e avisa quem criou', function () {
    [$owner, $company] = ownerWithCompany();
    $quote = sentQuote($company, ['created_by' => $owner->id, 'total' => '1000.00']);
    $url = publicQuoteUrl($quote);

    $this->postJson($url, ['decision' => 'accept'])->assertUnprocessable()->assertJsonValidationErrors('signer_name');

    $this->postJson($url, ['decision' => 'accept', 'signer_name' => 'Maria da Padaria'])
        ->assertOk()
        ->assertJsonPath('data.status.value', 'accepted')
        ->assertJsonPath('data.signer_name', 'Maria da Padaria')
        ->assertJsonPath('data.awaits_decision', false);

    $project = Project::query()->sole();
    $contract = Contract::query()->sole();

    expect($project->company_id)->toBe($company->id)
        ->and($project->client_id)->toBe($quote->client_id)
        ->and($project->billing_type)->toBe(BillingType::Hourly)
        ->and($project->hourly_rate)->toBe('100.00')
        ->and($project->budget)->toBe('1000.00')
        ->and($contract->number)->toBe('CTR-2026-0001')
        ->and($contract->signer_name)->toBe('Maria da Padaria')
        ->and($contract->project_id)->toBe($project->id)
        ->and($quote->refresh()->project_id)->toBe($project->id);

    Notification::assertSentTo($owner, QuoteDecidedNotification::class, fn ($n) => $n->accepted && $n->projectUlid === $project->ulid);

    $this->postJson($url, ['decision' => 'accept', 'signer_name' => 'Maria da Padaria'])
        ->assertStatus(409)->assertJsonPath('code', 'quote_not_open');
    expect(Contract::query()->count())->toBe(1);
});

it('projeto com item de valor fixo vira preço fechado', function () {
    [, $company] = ownerWithCompany();
    $quote = sentQuote($company);
    $quote->items()->create(['position' => 1, 'description' => 'Logo', 'type' => 'fixed', 'quantity' => '1', 'unit_price' => '500', 'total' => '500']);

    $this->postJson(publicQuoteUrl($quote), ['decision' => 'accept', 'signer_name' => 'Maria'])->assertOk();

    expect(Project::query()->sole()->billing_type)->toBe(BillingType::Fixed);
});

it('recusa guarda o comentário e avisa o dono quando ninguém criou', function () {
    [$owner, $company] = ownerWithCompany();
    $quote = sentQuote($company);

    $this->postJson(publicQuoteUrl($quote), ['decision' => 'reject', 'note' => 'Ficou acima do orçamento.'])
        ->assertOk()
        ->assertJsonPath('data.status.value', 'rejected');

    expect($quote->refresh()->decision_note)->toBe('Ficou acima do orçamento.')
        ->and(Project::query()->count())->toBe(0);
    Notification::assertSentTo($owner, QuoteDecidedNotification::class, fn ($n) => ! $n->accepted && $n->note === 'Ficou acima do orçamento.');
});

it('não aceita depois da validade', function () {
    [, $company] = ownerWithCompany();
    $quote = sentQuote($company, ['valid_until' => '2026-10-27']);
    $url = publicQuoteUrl($quote);

    $this->travelTo(now()->addDay());

    $this->getJson($url)->assertOk()->assertJsonPath('data.awaits_decision', false);
    $this->postJson($url, ['decision' => 'accept', 'signer_name' => 'Maria'])
        ->assertUnprocessable()->assertJsonPath('code', 'quote_expired');
});

it('job diário expira orçamentos vencidos sem resposta', function () {
    [, $company] = ownerWithCompany();
    $overdue = sentQuote($company, ['valid_until' => '2026-10-26']);
    $today = sentQuote($company, ['valid_until' => '2026-10-27']);
    $draft = Quote::factory()->for($company)->create(['valid_until' => '2026-10-01']);

    (new ExpireQuotes)->handle(app(QuoteService::class));

    expect($overdue->refresh()->status)->toBe(QuoteStatus::Expired)
        ->and($today->refresh()->status)->toBe(QuoteStatus::Sent)
        ->and($draft->refresh()->status)->toBe(QuoteStatus::Draft);
});

it('duplica como rascunho novo e baixa o PDF', function () {
    [$owner, $company] = ownerWithCompany();
    $quote = sentQuote($company);

    $copy = fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/company/quotes/{$quote->ulid}/duplicate")
        ->assertCreated()
        ->assertJsonPath('data.status.value', 'draft')
        ->assertJsonPath('data.title', $quote->title)
        ->assertJsonCount(1, 'data.items')
        ->json('data');

    expect($copy['number'])->not->toBe($quote->number);

    $response = fromSpa($this)->actingAs($owner, 'web')->get("/api/v1/company/quotes/{$quote->ulid}/pdf")->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->streamedContent())->toStartWith('%PDF');
});

it('lista e encerra contratos', function () {
    [$owner, $company] = ownerWithCompany();
    $quote = sentQuote($company);
    $this->postJson(publicQuoteUrl($quote), ['decision' => 'accept', 'signer_name' => 'Maria'])->assertOk();
    $contract = Contract::query()->sole();

    fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/contracts')
        ->assertOk()
        ->assertJsonPath('data.0.number', 'CTR-2026-0001')
        ->assertJsonPath('data.0.quote.number', $quote->number);

    fromSpa($this)->actingAs($owner, 'web')->putJson("/api/v1/company/contracts/{$contract->ulid}/status", ['status' => 'completed'])
        ->assertOk()->assertJsonPath('data.status.value', 'completed');
    fromSpa($this)->actingAs($owner, 'web')->putJson("/api/v1/company/contracts/{$contract->ulid}/status", ['status' => 'cancelled'])
        ->assertUnprocessable()->assertJsonPath('code', 'contract_not_active');
});
