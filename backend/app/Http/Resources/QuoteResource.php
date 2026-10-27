<?php

namespace App\Http\Resources;

use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quote
 */
class QuoteResource extends JsonResource
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
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'is_decided' => $this->status->isDecided(),
            ],
            'valid_until' => $this->valid_until->toDateString(),
            'introduction' => $this->introduction,
            'terms' => $this->terms,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'tax_rate' => $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'viewed_at' => $this->viewed_at?->toIso8601String(),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'signer_name' => $this->signer_name,
            'decision_note' => $this->decision_note,
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->ulid,
                'name' => $this->client->name,
                'email' => $this->client->email,
            ]),
            'items' => QuoteItemResource::collection($this->whenLoaded('items')),
            'project' => $this->whenLoaded('project', fn () => $this->project === null ? null : [
                'id' => $this->project->ulid,
                'name' => $this->project->name,
            ]),
            'contract' => $this->whenLoaded('contract', fn () => $this->contract === null ? null : [
                'id' => $this->contract->ulid,
                'number' => $this->contract->number,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
