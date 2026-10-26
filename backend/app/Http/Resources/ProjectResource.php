<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'description' => $this->description,
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'billing_type' => ['value' => $this->billing_type->value, 'label' => $this->billing_type->label()],
            'hourly_rate' => $this->hourly_rate,
            'budget' => $this->budget,
            'starts_on' => $this->starts_on?->toDateString(),
            'due_on' => $this->due_on?->toDateString(),
            'client' => $this->whenLoaded('client', fn () => $this->client === null ? null : [
                'id' => $this->client->ulid,
                'name' => $this->client->name,
            ]),
            'open_tasks_count' => $this->whenHas('open_tasks_count'),
            'done_tasks_count' => $this->whenHas('done_tasks_count'),
        ];
    }
}
