<?php

namespace App\Http\Resources;

use App\Models\BoardElement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BoardElement
 */
class BoardElementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'type' => $this->type->value,
            'x' => $this->x,
            'y' => $this->y,
            'width' => $this->width,
            'height' => $this->height,
            'rotation' => $this->rotation,
            'z_index' => $this->z_index,
            // Objeto vazio, não lista, quando não há propriedades.
            'props' => (object) $this->props,
        ];
    }
}
