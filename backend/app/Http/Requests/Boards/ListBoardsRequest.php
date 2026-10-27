<?php

namespace App\Http\Requests\Boards;

use App\DTOs\Boards\BoardFilters;
use Illuminate\Foundation\Http\FormRequest;

class ListBoardsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'project' => ['nullable', 'string', 'size:26'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function toFilters(): BoardFilters
    {
        return new BoardFilters(
            search: $this->filled('search') ? $this->string('search')->toString() : null,
            project: $this->filled('project') ? $this->string('project')->toString() : null,
            perPage: $this->integer('per_page', 24),
        );
    }
}
