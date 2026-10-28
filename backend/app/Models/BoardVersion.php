<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Foto do board num momento, para ver e voltar a uma versão anterior.
 *
 * @property string $ulid
 * @property bool $automatic
 * @property list<array<string, mixed>> $elements
 * @property int $element_count
 * @property string $content_hash
 * @property Carbon $created_at
 */
#[Fillable(['company_id', 'board_id', 'created_by', 'label', 'automatic', 'elements', 'element_count', 'content_hash'])]
class BoardVersion extends Model
{
    use BelongsToCompany, HasUlids;

    public const UPDATED_AT = null;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'automatic' => 'boolean',
            'elements' => 'array',
            'element_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Board, $this>
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
