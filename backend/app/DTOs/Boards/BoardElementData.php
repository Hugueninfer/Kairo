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

    /**
     * O elemento como a API devolve (com "id" em vez de "ulid"), para avisar quem está no board.
     *
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        $element = $this->toArray();
        unset($element['ulid']);

        return ['id' => $this->ulid, ...$element];
    }
}
