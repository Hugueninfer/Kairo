<?php

namespace App\Http\Resources;

use App\Enums\ReminderStage;
use App\Models\Company;
use App\Services\Billing\ReminderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
class BillingSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Company $company */
        $company = $this->resource;

        return [
            'reminders_enabled' => $this->reminders_enabled,
            'reminder_stages' => array_map(fn (ReminderStage $stage) => $stage->value, ReminderService::stagesOf($company)),
            'reminder_message' => $this->reminder_message,
        ];
    }
}
