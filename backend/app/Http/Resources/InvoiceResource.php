<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'number' => $this->number,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'awaits_payment' => $this->status->awaitsPayment(),
            ],
            'issue_date' => $this->issue_date->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'notes' => $this->notes,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,
            'amount_paid' => $this->amount_paid,
            'payment_url' => $this->payment_url,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->ulid,
                'name' => $this->client->name,
                'email' => $this->client->email,
            ]),
            'project' => $this->whenLoaded('project', fn () => $this->project === null ? null : [
                'id' => $this->project->ulid,
                'name' => $this->project->name,
            ]),
            'contract' => $this->whenLoaded('contract', fn () => $this->contract === null ? null : [
                'id' => $this->contract->ulid,
                'number' => $this->contract->number,
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (InvoiceItem $item) => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total' => $item->total,
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn (Payment $payment) => [
                'id' => $payment->ulid,
                'amount' => $payment->amount,
                'method' => ['value' => $payment->method->value, 'label' => $payment->method->label()],
                'paid_at' => $payment->paid_at->toIso8601String(),
                'via_gateway' => $payment->gateway !== null,
            ])),
            'time_entries_count' => $this->whenCounted('timeEntries'),
        ];
    }
}
