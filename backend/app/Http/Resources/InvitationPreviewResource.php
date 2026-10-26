<?php

namespace App\Http\Resources;

use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * O que a tela pública de aceite pode mostrar: sem ids internos nem dados da empresa além do nome.
 *
 * @mixin Invitation
 */
class InvitationPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'company_name' => $this->company->name,
            'invited_by' => $this->inviter->name,
            'email' => $this->email,
            'expires_at' => $this->expires_at->toIso8601String(),
        ];
    }
}
