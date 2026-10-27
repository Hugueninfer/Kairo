<?php

namespace App\Models;

use App\Enums\QuoteItemType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item do orçamento. Não tem company_id: é sempre acessado pelo orçamento, que já é da empresa.
 *
 * @property QuoteItemType $type
 * @property string $quantity
 * @property string $unit_price
 * @property string $total
 */
#[Fillable(['position', 'description', 'type', 'quantity', 'unit_price', 'total'])]
class QuoteItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => QuoteItemType::class,
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
