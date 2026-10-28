<?php

namespace App\Http\Requests\Reports;

use App\DTOs\Reports\HoursReportFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class HoursReportRequest extends FormRequest
{
    /** Período máximo de um relatório, para o cálculo continuar rápido. */
    private const MAX_DAYS = 366;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'group' => ['nullable', 'in:project,member,client'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['from' => 'início', 'to' => 'fim', 'group' => 'agrupamento'];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $days = strtotime((string) $this->input('to')) - strtotime((string) $this->input('from'));
            if ($days / 86400 >= self::MAX_DAYS) {
                $validator->errors()->add('to', 'O período pode ter no máximo um ano.');
            }
        }];
    }

    public function toFilters(): HoursReportFilters
    {
        /** @var 'project'|'member'|'client' $group */
        $group = $this->input('group', 'project');

        return new HoursReportFilters($this->string('from')->toString(), $this->string('to')->toString(), $group);
    }
}
