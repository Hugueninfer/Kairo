<?php

namespace App\Services\Billing;

use App\DTOs\Billing\ContractInvoiceData;
use App\DTOs\Billing\HoursInvoiceData;
use App\DTOs\Billing\InvoiceData;
use App\DTOs\Billing\InvoiceFilters;
use App\DTOs\Billing\InvoiceItemData;
use App\DTOs\Billing\PaymentData;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\Billing\ClientWithoutEmailException;
use App\Exceptions\Billing\InvoiceNotDraftException;
use App\Exceptions\Billing\InvoiceNotPayableException;
use App\Exceptions\Billing\InvoicePaidException;
use App\Exceptions\Billing\NoBillableHoursException;
use App\Exceptions\Billing\PaymentExceedsBalanceException;
use App\Exceptions\Quotes\DiscountTooHighException;
use App\Integrations\Payments\Data\ChargeRequest;
use App\Integrations\Payments\Data\Customer;
use App\Integrations\Payments\PaymentGatewayManager;
use App\Jobs\IssueInvoice;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Notifications\Billing\InvoiceIssuedNotification;
use App\Notifications\Billing\InvoicePaidNotification;
use App\Repositories\Contracts\ClientRepository;
use App\Repositories\Contracts\ContractRepository;
use App\Repositories\Contracts\InvoiceRepository;
use App\Repositories\Contracts\PaymentRepository;
use App\Repositories\Contracts\ProjectRepository;
use App\Repositories\Contracts\TimeEntryRepository;
use App\Support\Cents;
use App\Support\Money;
use App\Support\Pdf\InvoicePdf;
use App\Support\TenantContext;
use App\Support\Transactions\TransactionManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Faturas: criação (manual, por horas ou por contrato), envio com link de pagamento,
 * baixa dos pagamentos e cancelamento.
 */
class InvoiceService
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly PaymentRepository $payments,
        private readonly ClientRepository $clients,
        private readonly ProjectRepository $projects,
        private readonly ContractRepository $contracts,
        private readonly TimeEntryRepository $entries,
        private readonly PaymentGatewayManager $gateways,
        private readonly InvoicePdf $pdf,
        private readonly TenantContext $tenant,
        private readonly TransactionManager $transaction,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function list(InvoiceFilters $filters): LengthAwarePaginator
    {
        return $this->invoices->paginateFiltered(
            $filters->search,
            $filters->status,
            $filters->client === null ? null : $this->client($filters->client)->id,
            $filters->perPage,
        );
    }

    /**
     * @return array{receivable: string, overdue: string, received_this_month: string}
     */
    public function summary(): array
    {
        $company = $this->tenant->company();

        return $this->invoices->summary(now($company->timezone)->startOfMonth()->utc()->toDateTimeString());
    }

    public function show(Invoice $invoice): Invoice
    {
        return $this->invoices->loadDetails($invoice);
    }

    public function create(User $author, InvoiceData $data): Invoice
    {
        $client = $this->client($data->client);
        $project = $data->project === null ? null : $this->project($data->project);

        return $this->store($author, $client, $data->items, [
            'project_id' => $project?->id,
            'due_date' => $data->due_date,
            'notes' => $data->notes,
        ], $data->discount);
    }

    /**
     * Junta as horas aprovadas do projeto no período, um item por tarefa e valor/hora,
     * e prende os apontamentos à fatura para não serem cobrados de novo.
     */
    public function createFromHours(User $author, HoursInvoiceData $data): Invoice
    {
        $project = $this->project($data->project);
        $client = $project->client_id === null ? null : $this->clientById($project->client_id);
        $timezone = $this->tenant->company()->timezone;
        $from = Carbon::parse($data->from, $timezone)->startOfDay()->utc();
        $to = Carbon::parse($data->to, $timezone)->addDay()->startOfDay()->utc();

        $entries = $this->entries->billableForInvoice($project->id, $from, $to);

        if ($entries->isEmpty() || $client === null) {
            throw new NoBillableHoursException;
        }

        $items = $entries
            ->groupBy(fn (TimeEntry $entry) => ($entry->task_id ?? 0).'|'.$entry->hourly_rate)
            ->map(function ($group) use ($project): InvoiceItemData {
                /** @var TimeEntry $first */
                $first = $group->first();
                $minutes = (int) $group->sum('duration_minutes');

                return new InvoiceItemData(
                    description: $project->name.' · '.($first->task->title ?? 'horas sem tarefa'),
                    quantity: number_format($minutes / 60, 2, '.', ''),
                    unit_price: (string) $first->hourly_rate,
                );
            })
            ->values()
            ->all();

        $period = Carbon::parse($data->from)->format('d/m/Y').' a '.Carbon::parse($data->to)->format('d/m/Y');

        return $this->store($author, $client, $items, [
            'project_id' => $project->id,
            'due_date' => $data->due_date,
            'notes' => trim("Horas de {$period}.\n".($data->notes ?? '')),
        ], '0.00', $entries->modelKeys());
    }

    /**
     * Fatura o contrato inteiro ou uma parcela (percentual do total).
     */
    public function createFromContract(User $author, ContractInvoiceData $data): Invoice
    {
        $contract = $this->contracts->findByUlid($data->contract) ?? throw (new ModelNotFoundException)->setModel(Contract::class, [$data->contract]);
        $client = $this->clientById($contract->client_id);
        $amount = Cents::toDecimal(Cents::percent(Cents::from($contract->total), $data->percent));
        $share = $data->percent === '100.00' ? '' : ' ('.Money::quantity($data->percent).'%)';

        return $this->store($author, $client, [
            new InvoiceItemData("Contrato {$contract->number} · {$contract->title}{$share}", '1.00', $amount),
        ], [
            'project_id' => $contract->project_id,
            'contract_id' => $contract->id,
            'due_date' => $data->due_date,
            'notes' => $data->notes,
        ]);
    }

    public function update(Invoice $invoice, InvoiceData $data): Invoice
    {
        $this->ensureDraft($invoice);
        $client = $this->client($data->client);
        $project = $data->project === null ? null : $this->project($data->project);
        [$items, $totals] = $this->calculate($data->items, $data->discount);

        return $this->invoices->updateInvoice($invoice, [
            ...$totals,
            'client_id' => $client->id,
            'project_id' => $project?->id,
            'due_date' => $data->due_date,
            'notes' => $data->notes,
            'pdf_path' => null,
        ], $items);
    }

    public function delete(Invoice $invoice): void
    {
        $this->ensureDraft($invoice);
        $this->invoices->deleteInvoice($invoice);
    }

    /**
     * Fecha o rascunho e põe na fila a cobrança no gateway, o PDF e o e-mail ao cliente.
     */
    public function send(Invoice $invoice): Invoice
    {
        $this->ensureDraft($invoice);
        $invoice = $this->invoices->loadDetails($invoice);

        if (blank($invoice->client->email)) {
            throw new ClientWithoutEmailException;
        }

        return $this->transaction->run(function () use ($invoice): Invoice {
            $invoice = $this->invoices->updateInvoice($invoice, [
                'status' => InvoiceStatus::Open,
                'issue_date' => now($this->tenant->company()->timezone)->toDateString(),
                'sent_at' => now(),
                'gateway' => $this->gateways->gateway()->name(),
            ]);

            IssueInvoice::dispatch($invoice->ulid);

            return $invoice;
        });
    }

    /**
     * Chamado pela fila: cria a cobrança no gateway (uma vez só), gera o PDF e avisa o cliente.
     */
    public function issue(string $ulid): void
    {
        $invoice = $this->invoices->findForProcessing($ulid);

        if ($invoice === null || ! $invoice->status->awaitsPayment()) {
            return;
        }

        if ($invoice->gateway_charge_id === null) {
            $charge = $this->gateways->gateway($invoice->gateway)->createCharge(new ChargeRequest(
                reference: $invoice->ulid,
                description: "Fatura {$invoice->number} · {$invoice->company->name}",
                amount: Cents::toDecimal(Cents::from($invoice->total) - Cents::from($invoice->amount_paid)),
                dueDate: $invoice->due_date->toDateString(),
                customer: new Customer($invoice->client->name, $invoice->client->email, $invoice->client->document, $invoice->client->phone),
            ));

            $invoice = $this->invoices->updateInvoice($invoice, ['gateway_charge_id' => $charge->id, 'payment_url' => $charge->url]);
        }

        $path = $this->pdfFor($invoice);
        $company = $invoice->company;

        Notification::route('mail', [(string) $invoice->client->email => $invoice->client->name])->notify(new InvoiceIssuedNotification(
            companyName: $company->name,
            number: $invoice->number,
            total: Money::format($invoice->total, $company->currency),
            dueDate: $invoice->due_date->format('d/m/Y'),
            paymentUrl: (string) $invoice->payment_url,
            pdfPath: $path,
        ));
    }

    /**
     * Cancela a fatura (e a cobrança no gateway) e solta as horas para outra fatura.
     */
    public function cancel(Invoice $invoice): Invoice
    {
        if ($invoice->status === InvoiceStatus::Cancelled) {
            return $this->invoices->loadDetails($invoice);
        }

        if (Cents::from($invoice->amount_paid) > 0) {
            throw new InvoicePaidException;
        }

        if ($invoice->gateway !== null && $invoice->gateway_charge_id !== null) {
            $this->gateways->gateway($invoice->gateway)->cancelCharge($invoice->gateway_charge_id);
        }

        return $this->transaction->run(function () use ($invoice): Invoice {
            $this->invoices->releaseTimeEntries($invoice);

            return $this->invoices->updateInvoice($invoice, ['status' => InvoiceStatus::Cancelled, 'cancelled_at' => now()]);
        });
    }

    /**
     * Pagamento recebido fora do gateway (dinheiro, transferência).
     */
    public function recordPayment(User $recorder, Invoice $invoice, PaymentData $data): Invoice
    {
        $timezone = $this->tenant->company()->timezone;

        $this->applyPayment($invoice, [
            'method' => $data->method,
            'amount' => $data->amount,
            'paid_at' => Carbon::parse($data->paid_on, $timezone)->setTimeFrom(now($timezone))->utc(),
            'recorded_by' => $recorder->id,
        ]);

        return $this->invoices->loadDetails($invoice->refresh());
    }

    /**
     * Pagamento confirmado pelo gateway. Ignora o que já foi registrado (o gateway pode avisar duas vezes).
     */
    public function confirmGatewayPayment(string $gateway, string $chargeId, string $paymentId, ?string $amount, PaymentMethod $method, Carbon $paidAt): void
    {
        $invoice = $this->invoices->findByCharge($gateway, $chargeId);

        if ($invoice === null || $this->payments->existsForGateway($gateway, $paymentId)) {
            return;
        }

        $this->tenant->set($invoice->company);

        $this->applyPayment($invoice, [
            'gateway' => $gateway,
            'gateway_payment_id' => $paymentId,
            'method' => $method,
            'amount' => $amount ?? Cents::toDecimal(Cents::from($invoice->total) - Cents::from($invoice->amount_paid)),
            'paid_at' => $paidAt,
        ], allowOverpayment: true);
    }

    public function markOverdue(): int
    {
        // Roda às 03h UTC (meia-noite em Brasília): o que venceu até ontem fica vencido.
        return $this->invoices->markOverdue(now()->toDateString());
    }

    /**
     * Caminho do PDF no disco, gerando na hora se ainda não existir.
     */
    public function pdf(Invoice $invoice): string
    {
        return $this->pdfFor($this->invoices->loadDetails($invoice)->setRelation('company', $this->tenant->company()));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function applyPayment(Invoice $invoice, array $attributes, bool $allowOverpayment = false): void
    {
        $paid = $this->transaction->run(function () use ($invoice, $attributes, $allowOverpayment): bool {
            $locked = $this->invoices->lockForUpdate($invoice);

            if (! $locked->status->awaitsPayment()) {
                throw new InvoiceNotPayableException;
            }

            $balance = Cents::from($locked->total) - Cents::from($locked->amount_paid);

            if (! $allowOverpayment && Cents::from((string) $attributes['amount']) > $balance) {
                throw new PaymentExceedsBalanceException;
            }

            $this->payments->record([...$attributes, 'company_id' => $locked->company_id, 'invoice_id' => $locked->id]);
            $amountPaid = $this->payments->totalFor($locked);
            $settled = Cents::from($amountPaid) >= Cents::from($locked->total);

            $this->invoices->updateInvoice($locked, [
                'amount_paid' => $amountPaid,
                'status' => $settled ? InvoiceStatus::Paid : ($locked->status === InvoiceStatus::Overdue ? InvoiceStatus::Overdue : InvoiceStatus::PartiallyPaid),
                'paid_at' => $settled ? $attributes['paid_at'] : null,
            ]);

            return $settled;
        });

        if ($paid) {
            $this->notifyPaid($invoice->refresh()->load(['company.owner', 'client', 'creator']));
        }
    }

    private function notifyPaid(Invoice $invoice): void
    {
        ($invoice->creator ?? $invoice->company->owner)->notify(new InvoicePaidNotification(
            number: $invoice->number,
            clientName: $invoice->client->name,
            total: Money::format($invoice->total, $invoice->company->currency),
            invoiceUlid: $invoice->ulid,
        ));
    }

    /**
     * @param  list<InvoiceItemData>  $items
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $timeEntryIds
     */
    private function store(User $author, Client $client, array $items, array $attributes, string $discount = '0.00', array $timeEntryIds = []): Invoice
    {
        [$rows, $totals] = $this->calculate($items, $discount);
        $company = $this->tenant->company();

        return $this->transaction->run(fn (): Invoice => $this->invoices->createInvoice([
            ...$attributes,
            ...$totals,
            'client_id' => $client->id,
            'created_by' => $author->id,
            'number' => $this->invoices->nextNumber($company->id, now($company->timezone)->year),
            'status' => InvoiceStatus::Draft,
            'issue_date' => now($company->timezone)->toDateString(),
        ], $rows, $timeEntryIds));
    }

    /**
     * @param  list<InvoiceItemData>  $items
     * @return array{0: list<array<string, mixed>>, 1: array<string, string>}
     */
    private function calculate(array $items, string $discount): array
    {
        $rows = [];
        $subtotal = 0;

        foreach ($items as $position => $item) {
            $total = Cents::times($item->quantity, $item->unit_price);
            $subtotal += $total;
            $rows[] = [...$item->toArray(), 'position' => $position, 'total' => Cents::toDecimal($total)];
        }

        $discountCents = Cents::from($discount);

        if ($discountCents > $subtotal) {
            throw new DiscountTooHighException;
        }

        return [$rows, [
            'subtotal' => Cents::toDecimal($subtotal),
            'discount' => Cents::toDecimal($discountCents),
            'total' => Cents::toDecimal($subtotal - $discountCents),
        ]];
    }

    private function pdfFor(Invoice $invoice): string
    {
        if ($this->pdf->exists($invoice->pdf_path)) {
            return (string) $invoice->pdf_path;
        }

        $path = $this->pdf->render($invoice);
        $this->invoices->updateInvoice($invoice, ['pdf_path' => $path]);

        return $path;
    }

    private function ensureDraft(Invoice $invoice): void
    {
        if (! $invoice->status->isDraft()) {
            throw new InvoiceNotDraftException;
        }
    }

    private function client(string $ulid): Client
    {
        return $this->clients->findByUlid($ulid) ?? throw (new ModelNotFoundException)->setModel(Client::class, [$ulid]);
    }

    private function clientById(int $id): Client
    {
        $client = $this->clients->find($id);

        return $client instanceof Client ? $client : throw (new ModelNotFoundException)->setModel(Client::class, [$id]);
    }

    private function project(string $ulid): Project
    {
        return $this->projects->findByUlid($ulid) ?? throw (new ModelNotFoundException)->setModel(Project::class, [$ulid]);
    }
}
