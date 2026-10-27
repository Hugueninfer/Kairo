<?php

namespace App\Repositories\Eloquent;

use App\Models\Invoice;
use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepository;

class PaymentRepositoryEloquent extends BaseRepository implements PaymentRepository
{
    public function model(): string
    {
        return Payment::class;
    }

    public function existsForGateway(string $gateway, string $paymentId): bool
    {
        return Payment::query()
            ->withoutGlobalScope('company')
            ->where('gateway', $gateway)
            ->where('gateway_payment_id', $paymentId)
            ->exists();
    }

    public function record(array $attributes): Payment
    {
        return Payment::query()->create($attributes);
    }

    public function totalFor(Invoice $invoice): string
    {
        $total = Payment::query()->withoutGlobalScope('company')->where('invoice_id', $invoice->id)->sum('amount');

        return number_format((float) $total, 2, '.', '');
    }
}
