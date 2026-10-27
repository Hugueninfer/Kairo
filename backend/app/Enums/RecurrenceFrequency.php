<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use Illuminate\Support\Carbon;

enum RecurrenceFrequency: string implements HasLabel
{
    use HasOptions;

    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mensal',
            self::Quarterly => 'Trimestral',
            self::Yearly => 'Anual',
        };
    }

    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::Yearly => 12,
        };
    }

    /**
     * Próxima data de cobrança. Dia 31 vira o último dia dos meses mais curtos.
     */
    public function next(Carbon $date): Carbon
    {
        return $date->copy()->addMonthsNoOverflow($this->months());
    }

    /**
     * Como o período aparece no item da fatura: "11/2026", "11/2026 a 01/2027" ou "2026/2027".
     */
    public function periodLabel(Carbon $start): string
    {
        $end = $this->next($start)->subDay();

        return match ($this) {
            self::Monthly => $start->format('m/Y'),
            self::Quarterly => $start->format('m/Y').' a '.$end->format('m/Y'),
            self::Yearly => $start->format('d/m/Y').' a '.$end->format('d/m/Y'),
        };
    }
}
