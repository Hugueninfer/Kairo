<?php

namespace App\DTOs\Boards;

use App\DTOs\Data;
use App\Enums\BoardElementType;

final readonly class BoardElementData extends Data
{
    /**
     * @param  array<string, mixed>  $props
     */
    public function __construct(
        public string $ulid,
        public BoardElementType $type,
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public float $rotation,
        public int $z_index,
        public array $props,
    ) {}
}
