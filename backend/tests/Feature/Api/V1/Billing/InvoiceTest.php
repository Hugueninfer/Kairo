<?php

use App\Enums\ContractStatus;
use App\Enums\InvoiceStatus;
use App\Enums\SystemRole;
use App\Enums\TimeEntryStatus;
use App\Jobs\MarkOverdueInvoices;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Notifications\Billing\InvoiceIssuedNotification;
use App\Notifications\Billing\InvoicePaidNotification;
use App\Services\Billing\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
function invoicePayload(Client $client, array $overrides = []): array
{
    return [
        'client_id' => $client->ulid,
        'due_date' => '2026-11-10',
        'notes' => 'Pagamento em até 10 dias.',
        'discount' => '50',
        'items' => [
            ['description' => 'Manutenção mensal', 'quantity' => 1, 'unit_price' => '800'],
            ['description' => 'Horas extras', 'quantity' => '2.5', 'unit_price' => '120'],
        ],
        ...$overrides,
    ];
}

/**
 * Aviso do gateway simulado, como chegaria de fora.
 */
function fakeWebhook($test, Invoice $invoice, array $overrides = [])
{
    return $test->withHeader('X-Fake-Token', 'kairo-fake-token')->postJson('/api/v1/webhooks/payments/fake', [
        'id' => 'evt_1',
        'event' => 'payment.paid',
        'charge' => $invoice->gateway_charge_id,
        'payment' => 'pay_1',
        'amount' => $invoice->total,
        'method' => 'pix',
        ...$overrides,
    ]);
}

function approvedEntry(Project $project, User $user, string $start, int $minutes, array $attributes = []): TimeEntry
{
    return TimeEntry::factory()->create([
        'company_id' => $project->company_id,
        'project_id' => $project->id,
        'user_id' => $user->id,
        'started_at' => $start,
        'ended_at' => Carbon::parse($start)->addMinutes($minutes),
        'duration_minutes' => $minutes,
        'billable' => true,
        'hourly_rate' => '100.00',
        'status' => TimeEntryStatus::Approved,
        ...$attributes,
    ]);
}

it('cria fatura manual com número sequencial e totais', function () {
    [$owner, $company] = ownerWithCompany();
    $client = Client::factory()->for($company)->create();

    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/invoices', invoicePayload($client))
        ->assertCreated()
        ->assertJsonPath('data.number', 'FAT-2026-0001')
        ->assertJsonPath('data.status.value', 'draft')
        ->assertJsonPath('data.subtotal', '1100.00')
        ->assertJsonPath('data.total', '1050.00')
        ->assertJsonPath('data.items.1.total', '300.00');
});

it('o membro comum não vê faturas', function () {
    [, $company] = ownerWithCompany();

    fromSpa($this)->actingAs(memberOf($company), 'web')->getJson('/api/v1/company/invoices')->assertForbidden();
});

it('fatura as horas aprovadas do projeto, um item por tarefa, e não cobra duas vezes', function () {
    [$owner, $company] = ownerWithCompany();
    $client = Client::factory()->for($company)->create();
    $project = Project::factory()->for($company)->create(['client_id' => $client->id, 'name' => 'Site']);
    $task = Task::factory()->for($project)->create(['company_id' => $company->id, 'title' => 'Layout']);

    approvedEntry($project, $owner, '2026-10-05 13:00:00', 90, ['task_id' => $task->id]);
    approvedEntry($project, $owner, '2026-10-06 13:00:00', 60, ['task_id' => $task->id]);
    approvedEntry($project, $owner, '2026-10-07 13:00:00', 30);
    approvedEntry($project, $owner, '2026-10-08 13:00:00', 60, ['status' => TimeEntryStatus::Pending]);
    approvedEntry($project, $owner, '2026-11-02 13:00:00', 60);

    $payload = ['project_id' => $project->ulid, 'from' => '2026-10-01', 'to' => '2026-10-31', 'due_date' => '2026-11-10'];

    $response = fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/invoices/from-hours', $payload)
        ->assertCreated()
        ->assertJsonPath('data.client.id', $client->ulid)
        ->assertJsonPath('data.project.id', $project->ulid)
        ->assertJsonPath('data.time_entries_count', 3)
        ->assertJsonPath('data.total', '300.00');

    expect(collect($response->json('data.items'))->pluck('description', 'quantity')->all())
        ->toBe(['2.50' => 'Site · Layout', '0.50' => 'Site · horas sem tarefa']);

    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/invoices/from-hours', $payload)
        ->assertUnprocessable()->assertJsonPath('code', 'no_billable_hours');

    // Hora faturada não pode mais ser recusada.
    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/time-entries/review', [
        'ids' => TimeEntry::query()->whereNotNull('invoice_id')->pluck('ulid')->all(),
        'status' => 'rejected',
    ])->assertJsonPath('data.updated', 0);
});

it('apagar o rascunho solta as horas para outra fatura', function () {
    [$owner, $company] = ownerWithCompany();
    $project = Project::factory()->for($company)->create(['client_id' => Client::factory()->for($company)->create()->id]);
    approvedEntry($project, $owner, '2026-10-05 13:00:00', 60);

    $id = fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/invoices/from-hours', [
        'project_id' => $project->ulid, 'from' => '2026-10-01', 'to' => '2026-10-31', 'due_date' => '2026-11-10',
    ])->json('data.id');

    fromSpa($this)->actingAs($owner, 'web')->deleteJson("/api/v1/company/invoices/{$id}")->assertNoContent();

    expect(TimeEntry::query()->sole()->invoice_id)->toBeNull();
});

it('fatura uma parcela do contrato', function () {
    [$owner, $company] = ownerWithCompany();
    $quote = Quote::factory()->for($company)->create();
    $contract = (new Contract)->forceFill([
        'company_id' => $company->id, 'client_id' => $quote->client_id, 'quote_id' => $quote->id,
        'number' => 'CTR-2026-0001', 'title' => 'Site', 'total' => '4028.00', 'status' => ContractStatus::Active,
        'signed_at' => now(), 'signer_name' => 'Maria',
    ]);
    $contract->save();

    fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/invoices/from-contract', [
        'contract_id' => $contract->ulid, 'percent' => 50, 'due_date' => '2026-11-10',
    ])
        ->assertCreated()
        ->assertJsonPath('data.total', '2014.00')
        ->assertJsonPath('data.contract.number', 'CTR-2026-0001')
        ->assertJsonPath('data.items.0.description', 'Contrato CTR-2026-0001 · Site (50%)');
});

it('envia: cria a cobrança no gateway e manda o link e o PDF ao cliente', function () {
    [$owner, $company] = ownerWithCompany();
    $client = Client::factory()->for($company)->create(['email' => 'financeiro@padaria.test']);
    $id = fromSpa($this)->actingAs($owner, 'web')->postJson('/api/v1/company/invoices', invoicePayload($client))->json('data.id');

    fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/company/invoices/{$id}/send")
        ->assertOk()
        ->assertJsonPath('data.status.value', 'open');

    $invoice = Invoice::query()->where('ulid', $id)->sole();
    expect($invoice->gateway)->toBe('fake')
        ->and($invoice->gateway_charge_id)->toStartWith('fake_')
        ->and($invoice->payment_url)->toContain('/fake-checkout/');
    Storage::disk('local')->assertExists((string) $invoice->pdf_path);

    Notification::assertSentOnDemand(
        InvoiceIssuedNotification::class,
        fn ($notification, $channels, $notifiable) => array_key_exists('financeiro@padaria.test', $notifiable->routes['mail'])
            && $notification->paymentUrl === $invoice->payment_url,
    );

    fromSpa($this)->actingAs($owner, 'web')->putJson("/api/v1/company/invoices/{$id}", invoicePayload($client))
        ->assertUnprocessable()->assertJsonPath('code', 'invoice_not_draft');
});

it('o webhook dá baixa uma vez só e avisa quem emitiu', function () {
    [$owner, $company] = ownerWithCompany();
    $invoice = Invoice::factory()->for($company)->open()->create(['created_by' => $owner->id, 'total' => '1050.00']);

    fakeWebhook($this, $invoice)->assertOk();
    fakeWebhook($this, $invoice)->assertOk();
    fakeWebhook($this, $invoice, ['id' => 'evt_2'])->assertOk();

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->amount_paid)->toBe('1050.00')
        ->and($invoice->paid_at)->not->toBeNull()
        ->and($invoice->payments()->count())->toBe(1)
        ->and(WebhookEvent::query()->count())->toBe(2)
        ->and(WebhookEvent::query()->whereNull('processed_at')->count())->toBe(0);

    Notification::assertSentToTimes($owner, InvoicePaidNotification::class, 1);
});

it('recusa webhook sem a assinatura do gateway', function () {
    [, $company] = ownerWithCompany();
    $invoice = Invoice::factory()->for($company)->open()->create();

    $this->withHeader('X-Fake-Token', 'errado')->postJson('/api/v1/webhooks/payments/fake', ['id' => 'x', 'event' => 'payment.paid', 'charge' => $invoice->gateway_charge_id])
        ->assertForbidden()->assertJsonPath('code', 'invalid_webhook');
    $this->postJson('/api/v1/webhooks/payments/desconhecido', [])->assertNotFound();

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Open)
        ->and(WebhookEvent::query()->count())->toBe(0);
});

it('registra pagamento parcial à mão e depois quita', function () {
    [$owner, $company] = ownerWithCompany();
    $invoice = Invoice::factory()->for($company)->open()->create(['total' => '1000.00']);

    fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/company/invoices/{$invoice->ulid}/payments", [
        'amount' => '400', 'method' => 'transfer', 'paid_on' => '2026-10-27',
    ])->assertOk()->assertJsonPath('data.status.value', 'partially_paid')->assertJsonPath('data.amount_paid', '400.00');

    fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/company/invoices/{$invoice->ulid}/payments", [
        'amount' => '600.01', 'method' => 'cash', 'paid_on' => '2026-10-27',
    ])->assertUnprocessable()->assertJsonPath('code', 'payment_exceeds_balance');

    fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/company/invoices/{$invoice->ulid}/payments", [
        'amount' => '600', 'method' => 'cash', 'paid_on' => '2026-10-27',
    ])->assertOk()->assertJsonPath('data.status.value', 'paid')->assertJsonCount(2, 'data.payments');
});

it('cancela e solta as horas; com pagamento não cancela', function () {
    [$owner, $company] = ownerWithCompany();
    $project = Project::factory()->for($company)->create();
    $invoice = Invoice::factory()->for($company)->open()->create(['project_id' => $project->id]);
    $entry = approvedEntry($project, $owner, '2026-10-05 13:00:00', 60);
    $entry->forceFill(['invoice_id' => $invoice->id])->save();
    $paid = Invoice::factory()->for($company)->open()->create(['amount_paid' => '10.00', 'status' => InvoiceStatus::PartiallyPaid]);

    fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/company/invoices/{$invoice->ulid}/cancel")
        ->assertOk()->assertJsonPath('data.status.value', 'cancelled');
    fromSpa($this)->actingAs($owner, 'web')->postJson("/api/v1/company/invoices/{$paid->ulid}/cancel")
        ->assertUnprocessable()->assertJsonPath('code', 'invoice_paid');

    expect($entry->refresh()->invoice_id)->toBeNull();

    // Pagamento avisado depois do cancelamento não reabre a fatura.
    fakeWebhook($this, $invoice)->assertOk();
    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Cancelled)
        ->and(WebhookEvent::query()->sole()->error)->not->toBeNull();
});

it('job diário marca as vencidas e o resumo soma a receber', function () {
    [$owner, $company] = ownerWithCompany();
    $late = Invoice::factory()->for($company)->open()->create(['due_date' => '2026-10-26', 'total' => '300.00']);
    Invoice::factory()->for($company)->open()->create(['due_date' => '2026-10-27', 'total' => '200.00']);
    Invoice::factory()->for($company)->create(['due_date' => '2026-10-01']);

    (new MarkOverdueInvoices)->handle(app(InvoiceService::class));

    expect($late->refresh()->status)->toBe(InvoiceStatus::Overdue);

    fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/invoices')
        ->assertOk()
        ->assertJsonPath('summary.receivable', '500.00')
        ->assertJsonPath('summary.overdue', '300.00');
});

it('não mostra fatura de outra empresa e baixa o PDF', function () {
    [$owner, $company] = ownerWithCompany();
    $other = Invoice::factory()->create();
    $mine = Invoice::factory()->for($company)->create();
    $mine->items()->create(['position' => 0, 'description' => 'Serviço', 'quantity' => '1', 'unit_price' => '1000', 'total' => '1000']);

    fromSpa($this)->actingAs($owner, 'web')->getJson("/api/v1/company/invoices/{$other->ulid}")->assertNotFound();

    $response = fromSpa($this)->actingAs($owner, 'web')->get("/api/v1/company/invoices/{$mine->ulid}/pdf")->assertOk();
    expect($response->streamedContent())->toStartWith('%PDF');
});

it('o financeiro emite faturas', function () {
    [, $company] = ownerWithCompany();
    $client = Client::factory()->for($company)->create();

    fromSpa($this)->actingAs(memberOf($company, SystemRole::Finance), 'web')->postJson('/api/v1/company/invoices', invoicePayload($client))->assertCreated();
});
