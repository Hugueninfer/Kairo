<?php

namespace Tests\Fixtures;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum ExampleStatus: string implements HasLabel
{
    use HasOptions;

    case Draft = 'draft';
    case Sent = 'sent';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Sent => 'Enviado',
        };
    }
}
