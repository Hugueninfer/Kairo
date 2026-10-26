<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Colunas do quadro de tarefas, na ordem em que aparecem.
 */
enum TaskStatus: string implements HasLabel
{
    use HasOptions;

    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Review = 'review';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'A fazer',
            self::InProgress => 'Em andamento',
            self::Review => 'Em revisão',
            self::Done => 'Concluída',
        };
    }

    public function isDone(): bool
    {
        return $this === self::Done;
    }
}
