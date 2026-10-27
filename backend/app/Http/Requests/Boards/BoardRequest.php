<?php

namespace App\Http\Requests\Boards;

use App\DTOs\Boards\BoardData;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BoardRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(TenantContext $tenant): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'project_id' => ['nullable', 'string', Rule::exists('projects', 'ulid')->where('company_id', $tenant->id())->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['project_id' => 'projeto'];
    }

    public function toData(): BoardData
    {
        return new BoardData(
            title: $this->string('title')->trim()->toString(),
            project: $this->filled('project_id') ? $this->string('project_id')->toString() : null,
        );
    }
}
