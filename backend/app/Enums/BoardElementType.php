<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum BoardElementType: string implements HasLabel
{
    use HasOptions;

    case Note = 'note';
    case Rect = 'rect';
    case Ellipse = 'ellipse';
    case Text = 'text';
    case Arrow = 'arrow';

    public function label(): string
    {
        return match ($this) {
            self::Note => 'Nota',
            self::Rect => 'Retângulo',
            self::Ellipse => 'Elipse',
            self::Text => 'Texto',
            self::Arrow => 'Seta',
        };
    }
}
