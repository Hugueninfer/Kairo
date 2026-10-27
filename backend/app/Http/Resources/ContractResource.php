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
            'client' => $this->whenLoaded('client', fn () => ['id' => $this->client->ulid, 'name' => $this->client->name]),
            'project' => $this->whenLoaded('project', fn () => $this->project === null ? null : [
                'id' => $this->project->ulid,
                'name' => $this->project->name,
            ]),
            'quote' => $this->whenLoaded('quote', fn () => ['id' => $this->quote->ulid, 'number' => $this->quote->number]),
        ];
    }
}
