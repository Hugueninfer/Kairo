<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Etapas da régua de cobrança, contadas a partir do vencimento da fatura.
 */
enum ReminderStage: string implements HasLabel
{
    use HasOptions;

    case BeforeDue = 'before_due';
    case DueToday = 'due_today';
    case Overdue3 = 'overdue_3';
    case Overdue7 = 'overdue_7';

    public function label(): string
    {
        return match ($this) {
            self::BeforeDue => '3 dias antes do vencimento',
            self::DueToday => 'No dia do vencimento',
            self::Overdue3 => '3 dias depois do vencimento',
            self::Overdue7 => '7 dias depois do vencimento',
        };
    }

    /**
     * Dias depois do vencimento (negativo é antes).
     */
    public function daysFromDue(): int
    {
        return match ($this) {
            self::BeforeDue => -3,
            self::DueToday => 0,
            self::Overdue3 => 3,
            self::Overdue7 => 7,
        };
    }

    /**
     * Assunto do e-mail ao cliente.
     */
    public function subject(string $number, string $companyName): string
    {
        return match ($this) {
            self::BeforeDue => "Lembrete: a fatura {$number} de {$companyName} vence em 3 dias",
            self::DueToday => "A fatura {$number} de {$companyName} vence hoje",
            self::Overdue3, self::Overdue7 => "A fatura {$number} de {$companyName} está em atraso",
        };
    }

    /**
     * Etapas ligadas numa empresa nova.
     *
     * @return list<string>
     */
    public static function defaults(): array
    {
        return array_map(fn (self $stage) => $stage->value, self::cases());
    }
}
