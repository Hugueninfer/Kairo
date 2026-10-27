<?php

namespace App\Jobs;

use App\Services\Boards\BoardVersionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Guarda uma versão do board. As automáticas são únicas por board enquanto esperam na fila,
 * então uma rajada de autosaves vira uma versão só.
 */
class SnapshotBoard implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $boardId,
        public readonly ?int $userId = null,
        public readonly ?string $label = null,
        public readonly bool $automatic = false,
    ) {}

    public function uniqueId(): string
    {
        // Só as automáticas se juntam; cada versão pedida por alguém é guardada.
        return $this->automatic ? "auto-{$this->boardId}" : (string) Str::ulid();
    }

    public function handle(BoardVersionService $versions): void
    {
        $versions->take($this->boardId, $this->userId, $this->label, $this->automatic);
    }
}
