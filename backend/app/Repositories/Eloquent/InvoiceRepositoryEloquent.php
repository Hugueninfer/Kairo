<?php

namespace App\Repositories\Eloquent;

use App\Criteria\DocumentFilterCriteria;
use App\Criteria\SearchCriteria;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TimeEntry;
use App\Repositories\Contracts\InvoiceRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InvoiceRepositoryEloquent extends BaseRepository implements InvoiceRepository
{
    public function model(): string
    {
        return Invoice::class;
    }

    public function paginateFiltered(?string $search, ?InvoiceStatus $status, ?int $clientId, int $perPage): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, Invoice> */
        return $this->filteredQuery([
            new SearchCriteria($search, ['number']),
            new DocumentFilterCriteria($status, $clientId),
        ])
            ->with(['client:id,ulid,name,email,deleted_at', 'project:id,ulid,name,deleted_at'])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findByUlid(string $ulid): ?Invoice
    {
        /** @var Invoice|null */
        return $this->filteredQuery()->where('ulid', $ulid)->first();
    }

    public function findForProcessing(string $ulid): ?Invoice
    {
        return Invoice::query()
            ->withoutGlobalScope('company')
            ->with(['company.owner', 'client', 'items', 'creator'])
            ->where('ulid', $ulid)
            ->first();
    }

    public function findByCharge(string $gateway, string $chargeId): ?Invoice
    {
        return Invoice::query()
            ->withoutGlobalScope('company')
            ->with(['company.owner', 'client', 'creator'])
            ->where('gateway', $gateway)
            ->where('gateway_charge_id', $chargeId)
            ->first();
    }

    public function loadDetails(Invoice $invoice): Invoice
    {
        return $invoice->load([
            'client:id,ulid,name,email,document,phone,deleted_at',
            'project:id,ulid,name,deleted_at',
            'contract:id,ulid,number,title',
            'items',
            'payments',
        ])->loadCount('timeEntries');
    }

    public function lockForUpdate(Invoice $invoice): Invoice
    {
        /** @var Invoice */
        return Invoice::query()->withoutGlobalScope('company')->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
    }

    public function nextNumber(int $companyId, int $year): string
    {
        return $this->nextSequence($companyId, "FAT-{$year}-");
    }

    public function createInvoice(array $attributes, array $items, array $timeEntryIds = []): Invoice
    {
        return DB::transaction(function () use ($attributes, $items, $timeEntryIds): Invoice {
            $invoice = Invoice::query()->create($attributes);
            $invoice->items()->createMany($items);

            if ($timeEntryIds !== []) {
                TimeEntry::query()->whereKey($timeEntryIds)->whereNull('invoice_id')->update(['invoice_id' => $invoice->id]);
            }

            return $this->loadDetails($invoice);
        });
    }

    public function updateInvoice(Invoice $invoice, array $attributes, ?array $items = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $attributes, $items): Invoice {
            $invoice->update($attributes);

            if ($items !== null) {
                $invoice->items()->delete();
                $invoice->items()->createMany($items);
                $invoice->unsetRelation('items');
            }

            return $this->loadDetails($invoice);
        });
    }

    public function releaseTimeEntries(Invoice $invoice): void
    {
        TimeEntry::query()->withoutGlobalScope('company')->where('invoice_id', $invoice->id)->update(['invoice_id' => null]);
    }

    public function deleteInvoice(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $this->releaseTimeEntries($invoice);
            $invoice->delete();
        });
    }

    public function markOverdue(string $today): int
    {
        return Invoice::query()
            ->withoutGlobalScope('company')
            ->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::PartiallyPaid])
            ->whereDate('due_date', '<', $today)
            ->update(['status' => InvoiceStatus::Overdue, 'updated_at' => now()]);
    }

    public function summary(string $monthStart): array
    {
        $open = $this->filteredQuery()
            ->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::PartiallyPaid, InvoiceStatus::Overdue])
            ->selectRaw('coalesce(sum(total - amount_paid), 0) as receivable')
            ->selectRaw("coalesce(sum(case when status = 'overdue' then total - amount_paid else 0 end), 0) as overdue")
            ->toBase()
            ->first();

        $received = Payment::query()->where('paid_at', '>=', $monthStart)->sum('amount');

        return [
            'receivable' => number_format((float) ($open->receivable ?? 0), 2, '.', ''),
            'overdue' => number_format((float) ($open->overdue ?? 0), 2, '.', ''),
            'received_this_month' => number_format((float) $received, 2, '.', ''),
        ];
    }
}
