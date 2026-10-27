<?php

namespace App\Services\Billing;

use App\Enums\ReminderStage;
use App\Models\Company;
use App\Notifications\Billing\InvoiceReminderNotification;
use App\Repositories\Contracts\CompanyRepository;
use App\Repositories\Contracts\InvoiceReminderRepository;
use App\Repositories\Contracts\InvoiceRepository;
use App\Support\Cents;
use App\Support\Money;
use App\Support\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Régua de cobrança: lembra o cliente antes, no dia e depois do vencimento,
 * conforme as etapas que a empresa deixou ligadas.
 */
class ReminderService
{
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly InvoiceRepository $invoices,
        private readonly InvoiceReminderRepository $reminders,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @return Collection<int, int>
     */
    public function companiesToRemind(): Collection
    {
        return $this->companies->withRemindersEnabled();
    }

    /**
     * Envia os lembretes do dia de uma empresa. Devolve quantos saíram.
     */
    public function sendForCompany(int $companyId): int
    {
        $company = $this->companies->find($companyId);

        if (! $company instanceof Company || ! $company->reminders_enabled) {
            return 0;
        }

        $this->tenant->set($company);
        $today = now($company->timezone)->startOfDay();
        $sent = 0;

        foreach (self::stagesOf($company) as $stage) {
            $dueDate = $today->copy()->subDays($stage->daysFromDue())->toDateString();

            foreach ($this->invoices->awaitingReminder($dueDate, $stage) as $invoice) {
                // Fatura enviada hoje: o cliente acabou de receber o e-mail com o link.
                if ($invoice->sent_at !== null && $invoice->sent_at->gte($today)) {
                    continue;
                }

                if (! $this->reminders->recordOnce($invoice, $stage)) {
                    continue;
                }

                Notification::route('mail', [(string) $invoice->client->email => $invoice->client->name])
                    ->notify(new InvoiceReminderNotification(
                        stage: $stage,
                        companyName: $company->name,
                        number: $invoice->number,
                        balance: Money::format(Cents::toDecimal(Cents::from($invoice->total) - Cents::from($invoice->amount_paid)), $company->currency),
                        dueDate: $invoice->due_date->format('d/m/Y'),
                        paymentUrl: (string) $invoice->payment_url,
                        customMessage: $company->reminder_message,
                    ));

                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Etapas ligadas na empresa, na ordem da régua. Sem configuração, todas.
     *
     * @return list<ReminderStage>
     */
    public static function stagesOf(Company $company): array
    {
        $enabled = $company->reminder_stages ?? ReminderStage::defaults();

        return array_values(array_filter(
            ReminderStage::cases(),
            fn (ReminderStage $stage) => in_array($stage->value, $enabled, true),
        ));
    }
}
