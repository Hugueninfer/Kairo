<?php

namespace App\Models;

use App\Enums\ReminderStage;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Registro de cada lembrete enviado ao cliente (uma vez por etapa da régua).
 *
 * @property ReminderStage $stage
 * @property Carbon $sent_at
 */
#[Fillable(['company_id', 'invoice_id', 'stage', 'sent_at'])]
class InvoiceReminder extends Model
{
    use BelongsToCompany;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => ReminderStage::class,
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
