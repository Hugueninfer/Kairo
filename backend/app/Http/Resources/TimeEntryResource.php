<?php

namespace App\Http\Resources;

use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TimeEntry
 */
class TimeEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'description' => $this->description,
            'started_at' => $this->started_at->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'is_running' => $this->isRunning(),
            'duration_minutes' => $this->duration_minutes,
            'billable' => $this->billable,
            'hourly_rate' => $this->hourly_rate,
            'amount' => $this->amount(),
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'is_invoiced' => $this->invoice_id !== null,
            'user' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name]),
            'project' => $this->whenLoaded('project', fn () => ['id' => $this->project->ulid, 'name' => $this->project->name]),
            'task' => $this->whenLoaded('task', fn () => $this->task === null ? null : ['id' => $this->task->ulid, 'title' => $this->task->title]),
        ];
    }
}
