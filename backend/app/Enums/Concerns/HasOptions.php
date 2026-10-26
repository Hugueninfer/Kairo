<?php

namespace App\Enums\Concerns;

use App\Enums\Contracts\HasLabel;

/**
 * Utilitários para Enums com valor (BackedEnum) que implementam HasLabel.
 *
 * @phpstan-require-implements HasLabel
 */
trait HasOptions
{
    /**
     * @return list<string|int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Lista pronta para selects no front.
     *
     * @return list<array{value: string|int, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
