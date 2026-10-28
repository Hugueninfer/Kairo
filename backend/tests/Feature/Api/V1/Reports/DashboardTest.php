<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\SystemRole;
use App\Enums\TimeEntryStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\TimeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->travelTo(Carbon::parse('2026-10-15 15:00', 'UTC')));

function payment(Invoice $invoice, string $amount, string $paidAt): void
{
    Payment::query()->create([
        'company_id' => $invoice->company_id, 'invoice_id' => $invoice->id, 'method' => PaymentMethod::cases()[0],
        'amount' => $amount, 'paid_at' => Carbon::parse($paidAt, 'UTC'),
    ]);
}

it('mostra recebido no mês, a receber, vencido e a receita dos últimos 6 meses', function () {
    [$owner, $company] = ownerWithCompany();
    $paid = Invoice::factory()->create(['company_id' => $company->id, 'status' => InvoiceStatus::Paid]);
    Invoice::factory()->open()->create(['company_id' => $company->id, 'total' => '1000.00', 'amount_paid' => '250.00']);
    Invoice::factory()->create(['company_id' => $company->id, 'status' => InvoiceStatus::Overdue, 'total' => '300.00']);
    Invoice::factory()->create(['company_id' => $company->id, 'status' => InvoiceStatus::Draft, 'total' => '999.00']);

    payment($paid, '500.00', '2026-10-02 12:00');
    // 1º de outubro às 01:00 UTC ainda é 30 de setembro em São Paulo.
    payment($paid, '120.00', '2026-10-01 01:00');
    payment($paid, '80.00', '2026-05-10 12:00');
    payment($paid, '9999.00', '2026-03-10 12:00');

    $finance = fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/dashboard')
        ->assertOk()
        ->assertJsonPath('data.month', 'Outubro de 2026')
        ->json('data.finance');

    expect($finance)->toMatchArray([
        'received_this_month' => '500.00',
        'open_count' => 2,
        'open_total' => '1050.00',
        'overdue_count' => 1,
        'overdue_total' => '300.00',
    ])->and(array_column($finance['revenue'], 'month'))->toBe(['2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10'])
        ->and(array_column($finance['revenue'], 'total'))->toBe(['80.00', '0.00', '0.00', '0.00', '120.00', '500.00']);
});

it('soma as horas do mês e avisa dos projetos perto ou acima do orçamento', function () {
    [$owner, $company] = ownerWithCompany();
    $company->update(['default_hourly_rate' => '100.00']);
    $member = memberOf($company);
    $tight = Project::factory()->for($company)->create(['name' => 'Site', 'budget' => '1000.00', 'hourly_rate' => '100.00']);
    $relaxed = Project::factory()->for($company)->create(['name' => 'App', 'budget' => '10000.00']);
    Project::factory()->for($company)->create(['name' => 'Sem orçamento']);

    $entry = fn (Project $project, $user, int $minutes, array $extra = []) => TimeEntry::factory()->create([
        'project_id' => $project->id, 'user_id' => $user->id, 'duration_minutes' => $minutes,
        'started_at' => '2026-10-10 12:00', 'ended_at' => '2026-10-10 13:00', 'hourly_rate' => '100.00', ...$extra,
    ]);
    $entry($tight, $owner, 540);
    $entry($tight, $member, 60, ['billable' => false]);
    $entry($tight, $member, 600, ['status' => TimeEntryStatus::Rejected]);
    $entry($relaxed, $owner, 120, ['started_at' => '2026-09-10 12:00']);

    $data = fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/dashboard')->assertOk()->json('data');

    expect($data['hours'])->toBe(['mine' => ['total' => 540, 'billable' => 540], 'team' => ['total' => 600, 'billable' => 540]])
        ->and($data['projects_over_budget'])->toBe([
            ['id' => $tight->ulid, 'name' => 'Site', 'budget' => '1000.00', 'consumed' => '1000.00', 'percent' => 100],
        ]);
});

it('mostra a cada um só os blocos que a permissão dele libera', function () {
    [, $company] = ownerWithCompany();
    $member = memberOf($company);

    $data = fromSpa($this)->actingAs($member, 'web')->getJson('/api/v1/company/dashboard')->assertOk()->json('data');

    expect($data['finance'])->toBeNull()
        ->and($data['hours']['team'])->toBeNull()
        ->and($data['hours']['mine'])->toBe(['total' => 0, 'billable' => 0])
        ->and($data['projects_over_budget'])->toBe([]);

    $this->app['auth']->forgetGuards();
    $finance = fromSpa($this)->actingAs(memberOf($company, SystemRole::Finance), 'web')->getJson('/api/v1/company/dashboard')->assertOk()->json('data');
    expect($finance['finance'])->not->toBeNull();
});

it('não mistura números de outra empresa', function () {
    [$owner] = ownerWithCompany();
    [, $other] = ownerWithCompany();
    Invoice::factory()->open()->create(['company_id' => $other->id]);
    TimeEntry::factory()->create(['project_id' => Project::factory()->for($other)->create(['budget' => '10.00'])->id, 'started_at' => '2026-10-10 12:00']);

    $data = fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/dashboard')->assertOk()->json('data');

    expect($data['finance']['open_count'])->toBe(0)
        ->and($data['hours']['team']['total'])->toBe(0)
        ->and($data['projects_over_budget'])->toBe([]);
});

it('reflete na hora um pagamento novo, mesmo com o painel em cache', function () {
    [$owner, $company] = ownerWithCompany();
    $invoice = Invoice::factory()->open()->create(['company_id' => $company->id, 'total' => '1000.00']);

    fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/dashboard')
        ->assertJsonPath('data.finance.received_this_month', '0.00');

    payment($invoice, '400.00', '2026-10-15 12:00');

    fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/dashboard')
        ->assertJsonPath('data.finance.received_this_month', '400.00');
});
