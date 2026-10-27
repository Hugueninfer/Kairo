<?php

namespace App\Http\Resources;

use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contract
 */
class ContractResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'number' => $this->number,
            'title' => $this->title,
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'total' => $this->total,
            'terms' => $this->terms,
            'signed_at' => $this->signed_at->toIso8601String(),
            'signer_name' => $this->signer_name,
            'recurrence' => $this->billing_frequency === null ? null : [
                'frequency' => ['value' => $this->billing_frequency->value, 'label' => $this->billing_frequency->label()],
                'amount' => $this->recurring_amount,
                'next_billing_on' => $this->next_billing_on?->toDateString(),
                'ends_on' => $this->billing_ends_on?->toDateString(),
                'payment_term_days' => $this->payment_term_days,
                'auto_send' => $this->auto_send,
            ],
            'client' => $this->whenLoaded('client', fn () => ['id' => $this->client->ulid, 'name' => $this->client->name]),
            'project' => $this->whenLoaded('project', fn () => $this->project === null ? null : [
                'id' => $this->project->ulid,
                'name' => $this->project->name,
            ]),
            'quote' => $this->whenLoaded('quote', fn () => ['id' => $this->quote->ulid, 'number' => $this->quote->number]),
        ];
    }
}
