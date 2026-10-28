<?php

namespace App\Http\Resources;

use App\Models\BoardVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BoardVersion
 */
class BoardVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'label' => $this->label,
            'automatic' => $this->automatic,
            'element_count' => $this->element_count,
            'created_at' => $this->created_at->toIso8601String(),
            'author' => $this->whenLoaded('author', fn () => $this->author === null ? null : ['name' => $this->author->name]),
            // A cópia dos elementos só vem ao abrir uma versão, não na lista.
            'elements' => $this->when(
                array_key_exists('elements', $this->getAttributes()),
                fn () => array_map(fn (array $element) => [...$element, 'props' => (object) $element['props']], $this->elements),
            ),
        ];
    }
}
