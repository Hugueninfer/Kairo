<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    use HasOptions;

    case Pix = 'pix';
    case Boleto = 'boleto';
    case CreditCard = 'credit_card';
    case Transfer = 'transfer';
    case Cash = 'cash';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Pix => 'Pix',
            self::Boleto => 'Boleto',
            self::CreditCard => 'Cartão de crédito',
            self::Transfer => 'Transferência',
            self::Cash => 'Dinheiro',
            self::Other => 'Outro',
        };
    }
}
