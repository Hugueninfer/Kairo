<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum InvoiceStatus: string implements HasLabel
{
    use HasOptions;

    case Draft = 'draft';
    case Open = 'open';
    case PartiallyPaid = 'partially_paid';
    case Overdue = 'overdue';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Open => 'Em aberto',
            self::PartiallyPaid => 'Paga em parte',
            self::Overdue => 'Vencida',
            self::Paid => 'Paga',
            self::Cancelled => 'Cancelada',
        };
    }

    /**
     * Só o rascunho pode ter itens alterados ou ser apagado; depois de enviada, só se cancela.
     */
    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Enviada e ainda com valor a receber.
     */
    public function awaitsPayment(): bool
    {
        return in_array($this, [self::Open, self::PartiallyPaid, self::Overdue], true);
    }
}
