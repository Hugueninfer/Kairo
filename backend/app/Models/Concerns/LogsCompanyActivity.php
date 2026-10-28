<?php

namespace App\Models\Concerns;

use App\Enums\AuditSubject;
use App\Enums\Contracts\HasLabel;
use App\Models\Activity as CompanyActivity;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Grava no histórico da empresa a criação, a alteração e a exclusão do model.
 *
 * O model diz o que ele é no histórico (auditSubject), quais campos acompanhar
 * (auditedAttributes) e como se chama (auditLabel). Situações e outros enums ficam
 * gravados já com o texto em português do momento.
 */
trait LogsCompanyActivity
{
    use LogsActivity;

    abstract public static function auditSubject(): AuditSubject;

    /**
     * @return list<string>
     */
    abstract protected function auditedAttributes(): array;

    abstract public function auditLabel(): string;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(static::auditSubject()->value)
            ->logOnly($this->auditedAttributes())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(CompanyActivity $activity, string $eventName): void
    {
        $properties = $activity->properties ?? new Collection;
        $updates = ['label' => $this->auditLabel()];

        foreach (['attributes', 'old'] as $key) {
            $values = $properties->get($key);

            if (is_array($values)) {
                $updates[$key] = array_map(
                    fn (mixed $value) => $value instanceof HasLabel ? $value->label() : $value,
                    $this->withEnumLabels($values),
                );
            }
        }

        $activity->properties = $properties->merge($updates);
    }

    /**
     * Troca o valor gravado de campos com enum pelo enum, para mostrar o texto em português.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function withEnumLabels(array $values): array
    {
        $casts = $this->getCasts();

        foreach ($values as $field => $value) {
            $cast = $casts[$field] ?? null;

            if (is_string($cast) && enum_exists($cast) && is_subclass_of($cast, \BackedEnum::class) && (is_string($value) || is_int($value))) {
                $values[$field] = $cast::tryFrom($value) ?? $value;
            }
        }

        return $values;
    }
}
