<?php

namespace App\Http\Resources;

use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invitation
 */
class InvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'email' => $this->email,
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'expires_at' => $this->expires_at->toIso8601String(),
            'invited_by' => $this->whenLoaded('inviter', fn () => $this->inviter->name),
            'role' => $this->whenLoaded('role', fn () => $this->role === null ? null : ['id' => $this->role->id, 'name' => $this->role->name]),
        ];
    }
}
