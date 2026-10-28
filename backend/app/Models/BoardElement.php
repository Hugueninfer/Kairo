<?php

namespace App\Models;

use App\Enums\BoardElementType;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\BoardElementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um item do board: posição, tamanho e propriedades. Gravado em lote pelo autosave.
 *
 * @property string $ulid
 * @property BoardElementType $type
 * @property float $x
 * @property float $y
 * @property float $width
 * @property float $height
 * @property float $rotation
 * @property int $z_index
 * @property array<string, mixed> $props
 */
class BoardElement extends Model
{
    /** @use HasFactory<BoardElementFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BoardElementType::class,
            'x' => 'float',
            'y' => 'float',
            'width' => 'float',
            'height' => 'float',
            'rotation' => 'float',
            'z_index' => 'integer',
            'props' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Board, $this>
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }
}
