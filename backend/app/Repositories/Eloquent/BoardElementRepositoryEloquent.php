<?php

namespace App\Repositories\Eloquent;

use App\Models\Board;
use App\Models\BoardElement;
use App\Repositories\Contracts\BoardElementRepository;
use Illuminate\Support\Facades\DB;

class BoardElementRepositoryEloquent extends BaseRepository implements BoardElementRepository
{
    public function model(): string
    {
        return BoardElement::class;
    }

    public function countAfterSync(Board $board, array $ulids, array $deleting): int
    {
        $existing = BoardElement::query()->where('board_id', $board->id);
        $current = (clone $existing)->count();
        $new = count(array_unique($ulids)) - ($ulids === [] ? 0 : (clone $existing)->whereIn('ulid', $ulids)->count());
        $removed = $deleting === [] ? 0 : (clone $existing)->whereIn('ulid', $deleting)->count();

        return $current + $new - $removed;
    }

    public function syncElements(Board $board, array $upserts, array $deletes, ?int $userId): void
    {
        DB::transaction(function () use ($board, $upserts, $deletes, $userId): void {
            if ($deletes !== []) {
                BoardElement::query()->where('board_id', $board->id)->whereIn('ulid', $deletes)->delete();
            }

            if ($upserts === []) {
                return;
            }

            $now = now();
            $rows = array_map(fn (array $element) => [
                ...$element,
                'props' => json_encode($element['props'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'company_id' => $board->company_id,
                'board_id' => $board->id,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $upserts);

            // upsert pelo índice único (board + ulid): uma ida ao banco para o lote inteiro.
            BoardElement::query()->upsert(
                $rows,
                ['board_id', 'ulid'],
                ['type', 'x', 'y', 'width', 'height', 'rotation', 'z_index', 'props', 'updated_by', 'updated_at'],
            );
        });
    }
}
