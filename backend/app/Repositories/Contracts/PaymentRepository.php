<?php

namespace App\Repositories\Contracts;

use App\Models\Invoice;
use App\Models\Payment;
use Prettus\Repository\Contracts\RepositoryInterface;

interface PaymentRepository extends RepositoryInterface
{
    public function existsForGateway(string $gateway, string $paymentId): bool;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function record(array $attributes): Payment;

    /**
     * Soma dos pagamentos da fatura, em texto decimal.
     */
    public function totalFor(Invoice $invoice): string;
}
