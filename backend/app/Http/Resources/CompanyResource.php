<?php

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'document' => $this->document,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'default_hourly_rate' => $this->default_hourly_rate,
            'is_owner' => $user !== null && $this->isOwnedBy($user),
            'is_current' => $user !== null && $user->current_company_id === $this->id,
        ];
    }
}
