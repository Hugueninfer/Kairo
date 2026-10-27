<?php

use App\Enums\InvoiceStatus;
use App\Enums\ReminderStage;
use App\Enums\SystemRole;
use App\Jobs\SendInvoiceReminders;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Notifications\Billing\InvoiceReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 09h em Brasília.
    $this->travelTo(Carbon::parse('2026-10-27 12:00:00', 'UTC'));
    Notification::fake();
});

function openInvoice(Company $company, string $dueDate, array $attributes = []): Invoice
{
    $client = Client::factory()->for($company)->create(['email' => fake()->unique()->safeEmail()]);

    return Invoice::factory()->for($company)->open()->create([
        'client_id' => $client->id,
        'due_date' => $dueDate,
        'sent_at' => now()->subDays(10),
        'total' => '1000.00',
        ...$attributes,
    ]);
}

/**
 * @return list<string>
 */
function remindedStages(Invoice $invoice): array
{
    return $invoice->reminders()->pluck('stage')->map(fn (ReminderStage $stage) => $stage->value)->all();
}

it('lembra antes, no dia e depois do vencimento, uma vez só por etapa', function () {
    [, $company] = ownerWithCompany();
    $before = openInvoice($company, '2026-10-30');
    $today = openInvoice($company, '2026-10-27', ['amount_paid' => '400.00', 'status' => InvoiceStatus::PartiallyPaid]);
    $late3 = openInvoice($company, '2026-10-24', ['status' => InvoiceStatus::Overdue]);
    $late7 = openInvoice($company, '2026-10-20', ['status' => InvoiceStatus::Overdue]);
    $late5 = openInvoice($company, '2026-10-22', ['status' => InvoiceStatus::Overdue]);
    $paid = openInvoice($company, '2026-10-30', ['status' => InvoiceStatus::Paid]);

    SendInvoiceReminders::dispatchSync();
    SendInvoiceReminders::dispatchSync();

    expect(remindedStages($before))->toBe(['before_due'])
        ->and(remindedStages($today))->toBe(['due_today'])
        ->and(remindedStages($late3))->toBe(['overdue_3'])
        ->and(remindedStages($late7))->toBe(['overdue_7'])
        ->and(remindedStages($late5))->toBe([])
        ->and(remindedStages($paid))->toBe([]);

    Notification::assertSentOnDemandTimes(InvoiceReminderNotification::class, 4);
    Notification::assertSentOnDemand(
        InvoiceReminderNotification::class,
        fn (InvoiceReminderNotification $notification, $channels, $notifiable) => $notification->stage === ReminderStage::DueToday
            && $notification->balance === "R$\u{a0}600,00"
            && array_key_exists((string) $today->client->email, $notifiable->routes['mail']),
    );
});

it('não lembra fatura enviada hoje nem cliente sem e-mail', function () {
    [, $company] = ownerWithCompany();
    $sentToday = openInvoice($company, '2026-10-30', ['sent_at' => now()->subHour()]);
    $noEmail = openInvoice($company, '2026-10-30');
    $noEmail->client->forceFill(['email' => null])->save();

    SendInvoiceReminders::dispatchSync();

    expect(remindedStages($sentToday))->toBe([])
        ->and(remindedStages($noEmail))->toBe([]);
    Notification::assertNothingSent();
});

it('segue as etapas e o recado escolhidos pela empresa', function () {
    [, $company] = ownerWithCompany();
    $company->forceFill(['reminder_stages' => ['overdue_3'], 'reminder_message' => 'Qualquer dúvida, chame no WhatsApp.'])->save();
    $before = openInvoice($company, '2026-10-30');
    $late = openInvoice($company, '2026-10-24', ['status' => InvoiceStatus::Overdue]);

    $silent = Company::factory()->create(['reminders_enabled' => false]);
    $other = openInvoice($silent, '2026-10-30');

    SendInvoiceReminders::dispatchSync();

    expect(remindedStages($before))->toBe([])
        ->and(remindedStages($late))->toBe(['overdue_3'])
        ->and(remindedStages($other))->toBe([]);

    Notification::assertSentOnDemand(InvoiceReminderNotification::class, function (InvoiceReminderNotification $notification) {
        $mail = $notification->toMail(new stdClass);

        return $mail->subject === 'A fatura '.$notification->number.' de '.$notification->companyName.' está em atraso'
            && in_array('Qualquer dúvida, chame no WhatsApp.', $mail->introLines, true);
    });
});

it('mostra e salva a régua de lembretes da empresa', function () {
    [$owner, $company] = ownerWithCompany();

    fromSpa($this)->actingAs($owner, 'web')->getJson('/api/v1/company/billing-settings')
        ->assertOk()
        ->assertJsonPath('data.reminders_enabled', true)
        ->assertJsonPath('data.reminder_stages', ['before_due', 'due_today', 'overdue_3', 'overdue_7']);

    fromSpa($this)->actingAs($owner, 'web')->putJson('/api/v1/company/billing-settings', [
        'reminders_enabled' => true,
        'reminder_stages' => ['overdue_7', 'before_due'],
        'reminder_message' => '  Pix com desconto até o vencimento.  ',
    ])
        ->assertOk()
        ->assertJsonPath('data.reminder_stages', ['before_due', 'overdue_7'])
        ->assertJsonPath('data.reminder_message', 'Pix com desconto até o vencimento.');

    fromSpa($this)->actingAs($owner, 'web')->putJson('/api/v1/company/billing-settings', [
        'reminders_enabled' => true, 'reminder_stages' => ['todo_dia'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['reminder_stages.0']);
});

it('a fatura mostra os lembretes enviados', function () {
    [$owner, $company] = ownerWithCompany();
    $invoice = openInvoice($company, '2026-10-30');
    SendInvoiceReminders::dispatchSync();

    fromSpa($this)->actingAs($owner, 'web')->getJson("/api/v1/company/invoices/{$invoice->ulid}")
        ->assertOk()
        ->assertJsonPath('data.reminders.0.stage.label', '3 dias antes do vencimento');
});

it('o financeiro ajusta a régua; o membro comum não', function () {
    [, $company] = ownerWithCompany();
    $payload = ['reminders_enabled' => false, 'reminder_stages' => []];

    fromSpa($this)->actingAs(memberOf($company, SystemRole::Finance), 'web')->putJson('/api/v1/company/billing-settings', $payload)
        ->assertOk()->assertJsonPath('data.reminders_enabled', false);
});

it('o membro comum não vê a régua', function () {
    [, $company] = ownerWithCompany();

    fromSpa($this)->actingAs(memberOf($company), 'web')->getJson('/api/v1/company/billing-settings')->assertForbidden();
});
