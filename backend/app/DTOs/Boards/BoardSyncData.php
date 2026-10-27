<?php

namespace App\DTOs\Boards;

use App\DTOs\Data;

/**
 * Lote do autosave: elementos criados ou alterados e os apagados desde o último envio.
 */
final readonly class BoardSyncData extends Data
{
    /**
     * @param  list<BoardElementData>  $upserts
     * @param  list<string>  $deletes
     */
    public function __construct(
        public array $upserts,
        public array $deletes,
    ) {}
}
