<?php

namespace App\Http\Requests\Timesheet;

use App\DTOs\Timesheet\TimeEntryFilters;
use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Consulta de horas (lista, planilha e CSV). Sem a permissão de ver a equipe toda,
 * a pessoa só enxerga as próprias horas.
 */
class TimesheetQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->filled('user') || $this->integer('user') === $this->authUser()->id) {
            return true;
        }

        return $this->authUser()->can(Permission::TimesheetViewAll->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['required_without:week', 'date_format:Y-m-d'],
            'to' => ['required_without:week', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.$this->maxTo()],
            'week' => ['nullable', 'date_format:Y-m-d'],
            'user' => ['nullable', 'integer'],
            'all' => ['nullable', 'boolean'],
            'project' => ['nullable', 'string', 'size:26'],
        ];
    }

    /**
     * Pessoa consultada: a escolhida, todos (all=1, só com permissão) ou quem pede.
     */
    public function userId(): ?int
    {
        if ($this->filled('user')) {
            return $this->integer('user');
        }

        if ($this->boolean('all') && $this->authUser()->can(Permission::TimesheetViewAll->value)) {
            return null;
        }

        return $this->authUser()->id;
    }

    public function toFilters(): TimeEntryFilters
    {
        return new TimeEntryFilters(
            from: $this->string('from')->toString(),
            to: $this->string('to')->toString(),
            userId: $this->userId(),
            project: $this->filled('project') ? $this->string('project')->toString() : null,
        );
    }

    public function week(): string
    {
        return $this->filled('week') ? $this->string('week')->toString() : now()->toDateString();
    }

    private function authUser(): User
    {
        /** @var User */
        return $this->user();
    }

    /**
     * Períodos de no máximo um ano, para a consulta não ficar pesada.
     */
    private function maxTo(): string
    {
        $from = $this->string('from')->toString();

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) === 1
            ? Carbon::parse($from)->addYear()->toDateString()
            : now()->addYear()->toDateString();
    }
}
