<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum ProjectStatus: string implements HasLabel
{
    use HasOptions;

    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Em andamento',
            self::Paused => 'Pausado',
            self::Completed => 'Concluído',
            self::Archived => 'Arquivado',
        };
    }

    /**
     * Projetos encerrados não recebem tarefas novas.
     */
    public function acceptsTasks(): bool
    {
        return $this === self::Active || $this === self::Paused;
    }
}
