<?php

namespace App\Events\Boards;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Uma versão nova do board ficou pronta (a foto é tirada na fila): o histórico aberto se atualiza.
 */
class BoardVersionSaved implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(
        public readonly string $board,
        public readonly string $version,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel("board.{$this->board}");
    }

    public function broadcastAs(): string
    {
        return 'version.saved';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return ['id' => $this->version];
    }
}
