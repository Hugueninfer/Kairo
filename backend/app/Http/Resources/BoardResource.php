<?php

namespace App\Http\Resources;

use App\Models\Board;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Board
 */
class BoardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'title' => $this->title,
            'updated_at' => $this->updated_at->toIso8601String(),
            // Link de leitura para o cliente, quando o board está compartilhado.
            'share_url' => $this->share_token === null ? null : rtrim((string) config('app.frontend_url'), '/').'/b/'.$this->share_token,
            'project' => $this->whenLoaded('project', fn () => $this->project === null ? null : [
                'id' => $this->project->ulid,
                'name' => $this->project->name,
            ]),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator === null ? null : ['name' => $this->creator->name]),
            'elements_count' => $this->whenCounted('elements'),
            'elements' => BoardElementResource::collection($this->whenLoaded('elements')),
        ];
    }
}
