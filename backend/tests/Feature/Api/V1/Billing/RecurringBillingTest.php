<?php

use App\Enums\ContractStatus;
use App\Enums\InvoiceStatus;
use App\Enums\RecurrenceFrequency;
use App\Enums\SystemRole;
use App\Jobs\GenerateRecurringInvoices;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Quote;
use App\Notifications\Billing\InvoiceIssuedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 10h em Brasília.
    $this->travelTo(Carbon::parse('2026-10-27 13:00:00', 'UTC'));
    Notification::fake();
    Storage::fake('local');
});

function signedContract(Company $company, array $attributes = []): Contract
{
    $client = Client::factory()->for($company)->create(['email' => 'financeiro@padaria.test']);
    $quote = Quote::factory()->for($company)->create(['client_id' => $client->id]);
    $contract = (new Contract)->forceFill([
        'company_id' => $company->id, 'client_id' => $client->id, 'quote_id' => $quote->id,
        'number' => 'CTR-2026-0001', 'title' => 'Manutenção do site', 'total' => '6000.00',
        'status' => ContractStatus::Active, 'signed_at' => now(), 'signer_name' => 'Maria',
        ...$attributes,
    ]);
    $contract->save();

    return $contract;
}

function recurringContract(Company $company, array $attributes = []): Contract
{
    return signedContract($company, [
        'billing_frequency' => RecurrenceFrequency::Monthly,
        'recurring_amount' => '500.00',
        'next_billing_on' => '2026-10-27',
        'payment_term_days' => 5,
        'auto_send' => true,
        ...$attributes,
    ]);
}

it('liga a cobrança recorrente do contrato', function () {
    [$owner, $company] = ownerWithCompany();
    $contract = signedContract($company);

    fromSpa($this)->actingAs($owner, 'web')->putJson("/api/v1/company/contracts/{$contract->ulid}/recurrence", [
        'frequency' => 'monthly', 'amount' => '500', 'starts_on' => '2026-11-01', 'payment_term_days' => 10,
    ])
        ->assertOk()
        ->assertJsonPath('data.recurrence.frequency.label', 'Mensal')
        ->assertJsonPath('data.recurrence.amount', '500.00')
        ->assertJsonPath('data.recurrence.next_billing_on', '2026-11-01')
        ->assertJsonPath('data.recurrence.payment_term_days', 10)
        ->assertJsonPath('data.recurrence.auto_send', true);

    fromSpa($this)->actingAs($owner, 'web')->putJson("/api/v1/company/contracts/{$contract->ulid}/recurrence", ['frequency' => null])
        ->assertOk()
        ->assertJsonPath('data.recurrence', null);
});

it('valida a recorrência e só aceita contrato vigente', function () {
    [$owner, $company] = ownerWithCompany();
    $contract = signedContract($company);

    fromSpa($this)->actingAs($owner, 'web')->putJson("/api/v1/company/contracts/{$contract->ulid}/recurrence", [
        'frequency' => 'monthly', 'starts_on' => '2026-10-20', 'ends_on' => '2026-10-01',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount', 'starts_on', 'ends_on'])
        ->assertJsonPath('errors.amount.0', 'O campo valor é obrigatório.');

    $contract->forceFill(['status' => ContractStatus::Cancelled])->save();

    fromSpa($this)->actingAs($owner, 'web')->putJson("/api/v1/company/contracts/{$contract->ulid}/recurrence", [
        'frequency' => 'monthly', 'amount' => '500', 'starts_on' => '2026-11-01',
    ])->assertUnprocessable()->assertJsonPath('code', 'contract_not_active');
});

it('o membro comum não mexe na recorrência', function () {
    [, $company] = ownerWithCompany();
    $contract = signedContract($company);

    fromSpa($this)->actingAs(memberOf($company), 'web')->putJson("/api/v1/company/contracts/{$contract->ulid}/recurrence", [
        'frequency' => 'monthly', 'amount' => '500', 'starts_on' => '2026-11-01',
    ])->assertForbidden();
});

it('no dia da cobrança gera a fatura, envia ao cliente e agenda a próxima', function () {
    [$owner, $company] = ownerWithCompany();
    $contract = recurringContract($company);

    GenerateRecurringInvoices::dispatchSync();

    $invoice = Invoice::query()->sole();
    expect($invoice->status)->toBe(InvoiceStatus::Open)
        ->and($invoice->total)->toBe('500.00')
        ->and($invoice->contract_id)->toBe($contract->id)
        ->and($invoice->created_by)->toBeNull()
        ->and($invoice->due_date->toDateString())->toBe('2026-11-01')
        ->and($invoice->payment_url)->not->toBeNull()
        ->and($invoice->items()->sole()->description)->toBe('Contrato CTR-2026-0001 · Manutenção do site · Mensal 10/2026')
        ->and($contract->refresh()->next_billing_on->toDateString())->toBe('2026-11-27');

    Notification::assertSentOnDemand(InvoiceIssuedNotification::class);

    // Rodar de novo no mesmo dia não duplica.
    GenerateRecurringInvoices::dispatchSync();
    expect(Invoice::query()->count())->toBe(1);

    $this->travelTo(Carbon::parse('2026-11-27 13:00:00', 'UTC'));
    GenerateRecurringInvoices::dispatchSync();

    expect(Invoice::query()->count())->toBe(2)
        ->and(Invoice::query()->latest('id')->first()->items()->sole()->description)->toEndWith('Mensal 11/2026')
        ->and($contract->refresh()->next_billing_on->toDateString())->toBe('2026-12-27');
});

it('respeita o fuso da empresa: antes da meia-noite em Brasília ainda não cobra', function () {
    [, $company] = ownerWithCompany();
    recurringContract($company, ['next_billing_on' => '2026-10-28']);

    // 28/10 01h UTC = 27/10 22h em Brasília.
    $this->travelTo(Carbon::parse('2026-10-28 01:00:00', 'UTC'));
    GenerateRecurringInvoices::dispatchSync();
    expect(Invoice::query()->count())->toBe(0);

    $this->travelTo(Carbon::parse('2026-10-28 09:00:00', 'UTC'));
    GenerateRecurringInvoices::dispatchSync();
    expect(Invoice::query()->count())->toBe(1);
});

it('sem envio automático deixa a fatura em rascunho; contrato encerrado não cobra', function () {
    [, $company] = ownerWithCompany();
    recurringContract($company, ['auto_send' => false]);
    GenerateRecurringInvoices::dispatchSync();

    expect(Invoice::query()->sole()->status)->toBe(InvoiceStatus::Draft);
    Notification::assertNothingSent();

    $cancelled = recurringContract(Company::factory()->create(), ['status' => ContractStatus::Cancelled]);
    GenerateRecurringInvoices::dispatchSync();

    expect(Invoice::query()->where('contract_id', $cancelled->id)->exists())->toBeFalse();
});

it('para depois da última cobrança e recupera períodos atrasados', function () {
    [, $company] = ownerWithCompany();
    $contract = recurringContract($company, [
        'billing_frequency' => RecurrenceFrequency::Quarterly,
        'next_billing_on' => '2026-01-31',
        'billing_ends_on' => '2026-08-31',
        'auto_send' => false,
    ]);

    GenerateRecurringInvoices::dispatchSync();

    // 31/01, 30/04 e 31/07 (o dia 31 vira 30 em abril); 31/10 passa do fim.
    expect(Invoice::query()->orderBy('billing_period_start')->pluck('billing_period_start')->map->toDateString()->all())
        ->toBe(['2026-01-31', '2026-04-30', '2026-07-30'])
        ->and($contract->refresh()->next_billing_on)->toBeNull();
});

it('o financeiro configura a recorrência', function () {
    [, $company] = ownerWithCompany();
    $contract = signedContract($company);

    fromSpa($this)->actingAs(memberOf($company, SystemRole::Finance), 'web')->putJson("/api/v1/company/contracts/{$contract->ulid}/recurrence", [
        'frequency' => 'yearly', 'amount' => '1200', 'starts_on' => '2026-10-27', 'auto_send' => false,
    ])->assertOk()->assertJsonPath('data.recurrence.auto_send', false);
});
