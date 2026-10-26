<?php

namespace App\Http\Requests\Projects;

use App\DTOs\Projects\ProjectFilters;
use App\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProjectsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'client' => ['nullable', 'string', 'size:26'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function toFilters(): ProjectFilters
    {
        return new ProjectFilters(
            search: $this->filled('search') ? $this->string('search')->toString() : null,
            status: $this->enum('status', ProjectStatus::class),
            client: $this->filled('client') ? $this->string('client')->toString() : null,
            perPage: $this->integer('per_page', 20),
        );
    }
}
