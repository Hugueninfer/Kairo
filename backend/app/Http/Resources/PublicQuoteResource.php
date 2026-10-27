<?php

namespace App\Http\Resources;

use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * O que o cliente vê pelo link público: sem ids internos, sem dados de quem criou.
 *
 * @mixin Quote
 */
class PublicQuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'title' => $this->title,
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'awaits_decision' => $this->status->awaitsDecision()
                && $this->valid_until->toDateString() >= now($this->company->timezone)->toDateString(),
            'valid_until' => $this->valid_until->toDateString(),
            'introduction' => $this->introduction,
            'terms' => $this->terms,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'tax_rate' => $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'currency' => $this->company->currency,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'signer_name' => $this->signer_name,
            'company' => ['name' => $this->company->name],
            'client' => ['name' => $this->client->name],
            'items' => QuoteItemResource::collection($this->items),
        ];
    }
}
