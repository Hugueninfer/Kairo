<?php

namespace App\Http\Resources;

use App\Models\Board;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Board visto pelo link de leitura: só o que o cliente precisa para ver o quadro.
 *
 * @mixin Board
 */
class PublicBoardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'company' => ['name' => $this->company->name],
            'updated_at' => $this->updated_at->toIso8601String(),
            'elements' => BoardElementResource::collection($this->elements),
        ];
    }
}
