<?php

namespace App\Events\Boards;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Avisa quem está no board que os elementos mudaram (autosave de alguém ou versão restaurada).
 *
 * Vai pela fila, para o autosave não depender do servidor de WebSocket estar no ar.
 */
class BoardElementsChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /** O Reverb (como o Pusher) limita o tamanho da mensagem; lotes maiores viram só um aviso para recarregar. */
    public const MAX_PAYLOAD_BYTES = 8000;

    /**
     * @param  array{id: int, name: string}  $editor
     * @param  list<array<string, mixed>>  $upserts  elementos no formato da API
     * @param  list<string>  $deletes
     */
    public function __construct(
        public readonly string $board,
        public readonly array $editor,
        public readonly array $upserts = [],
        public readonly array $deletes = [],
        public readonly bool $reload = false,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel("board.{$this->board}");
    }

    public function broadcastAs(): string
    {
        return 'elements.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $reload = ['editor' => $this->editor, 'reload' => true, 'upserts' => [], 'deletes' => []];

        if ($this->reload) {
            return $reload;
        }

        $payload = [
            'editor' => $this->editor,
            'reload' => false,
            // Objeto vazio, não lista, quando o elemento não tem propriedades (igual à API).
            'upserts' => array_map(fn (array $element) => [...$element, 'props' => (object) $element['props']], $this->upserts),
            'deletes' => $this->deletes,
        ];

        return strlen((string) json_encode($payload)) > self::MAX_PAYLOAD_BYTES ? $reload : $payload;
    }
}
