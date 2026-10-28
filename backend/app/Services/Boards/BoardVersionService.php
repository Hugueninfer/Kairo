<?php

namespace App\Services\Boards;

use App\Events\Boards\BoardElementsChanged;
use App\Events\Boards\BoardVersionSaved;
use App\Jobs\SnapshotBoard;
use App\Models\Board;
use App\Models\BoardVersion;
use App\Models\User;
use App\Repositories\Contracts\BoardElementRepository;
use App\Repositories\Contracts\BoardRepository;
use App\Repositories\Contracts\BoardVersionRepository;
use App\Support\Transactions\TransactionManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Histórico de versões do board: fotos automáticas enquanto se edita, versões salvas
 * por alguém e restauração.
 */
class BoardVersionService
{
    /** Intervalo mínimo entre duas versões automáticas do mesmo board. */
    public const AUTOMATIC_EVERY_MINUTES = 10;

    /** Quantas versões automáticas cada board guarda (as salvas por alguém ficam sempre). */
    public const KEEP_AUTOMATIC = 50;

    public function __construct(
        private readonly BoardRepository $boards,
        private readonly BoardElementRepository $elements,
        private readonly BoardVersionRepository $versions,
        private readonly TransactionManager $transaction,
    ) {}

    /**
     * @return LengthAwarePaginator<int, BoardVersion>
     */
    public function list(Board $board, int $perPage = 20): LengthAwarePaginator
    {
        return $this->versions->paginateForBoard($board, $perPage);
    }

    public function show(Board $board, string $ulid): BoardVersion
    {
        return $this->versions->findForBoard($board, strtolower($ulid))
            ?? throw (new ModelNotFoundException)->setModel(BoardVersion::class, [$ulid]);
    }

    /**
     * Versão pedida por alguém: a foto é tirada na fila.
     */
    public function request(User $author, Board $board, ?string $label): void
    {
        SnapshotBoard::dispatch($board->id, $author->id, $label);
    }

    /**
     * Chamado a cada autosave: agenda uma versão automática se a última já tem um tempo.
     */
    public function snapshotIfDue(Board $board): void
    {
        $latest = $this->versions->latestFor($board);

        if ($latest === null || $latest->created_at->lte(now()->subMinutes(self::AUTOMATIC_EVERY_MINUTES))) {
            SnapshotBoard::dispatch($board->id, automatic: true);
        }
    }

    /**
     * Tira a foto do board (roda na fila). Versão automática igual à anterior não é guardada.
     */
    public function take(int $boardId, ?int $userId, ?string $label, bool $automatic): ?BoardVersion
    {
        $board = $this->boards->findById($boardId);

        if ($board === null) {
            return null;
        }

        return $this->snapshot($board, $userId, $label, $automatic);
    }

    /**
     * Volta o board para a versão escolhida. Antes guarda o estado atual, para dar para desfazer.
     */
    public function restore(User $editor, Board $board, string $ulid): Board
    {
        $version = $this->show($board, $ulid);

        $restored = $this->transaction->run(function () use ($editor, $board, $version): Board {
            $this->snapshot($board, $editor->id, 'Antes de restaurar uma versão', false);

            $keep = array_map(fn (array $element) => (string) $element['id'], $version->elements);
            $this->elements->syncElements(
                $board,
                array_map(fn (array $element) => $this->toRow($element), $version->elements),
                array_values(array_diff($this->elements->ulidsOf($board), $keep)),
                $editor->id,
            );

            return $this->boards->touch($board);
        });

        // Todo mundo que está no board recarrega; quem restaurou já recebe o board na resposta.
        event((new BoardElementsChanged($board->ulid, ['id' => $editor->id, 'name' => $editor->name], reload: true))->dontBroadcastToCurrentUser());

        return $this->boards->loadDetails($restored);
    }

    private function snapshot(Board $board, ?int $userId, ?string $label, bool $automatic): ?BoardVersion
    {
        $elements = $this->elements->snapshotOf($board);
        $hash = hash('sha256', (string) json_encode($elements));

        if ($automatic && $this->versions->latestFor($board)?->content_hash === $hash) {
            return null;
        }

        $version = $this->versions->createVersion([
            'company_id' => $board->company_id,
            'board_id' => $board->id,
            'created_by' => $userId,
            'label' => $label,
            'automatic' => $automatic,
            'elements' => $elements,
            'element_count' => count($elements),
            'content_hash' => $hash,
        ]);

        if ($automatic) {
            $this->versions->pruneAutomatic($board, self::KEEP_AUTOMATIC);
        }

        event(new BoardVersionSaved($board->ulid, $version->ulid));

        return $version;
    }

    /**
     * Elemento guardado na versão (formato da API) de volta para colunas da tabela.
     *
     * @param  array<string, mixed>  $element
     * @return array<string, mixed>
     */
    private function toRow(array $element): array
    {
        return [
            'ulid' => $element['id'],
            'type' => $element['type'],
            'x' => $element['x'],
            'y' => $element['y'],
            'width' => $element['width'],
            'height' => $element['height'],
            'rotation' => $element['rotation'],
            'z_index' => $element['z_index'],
            'props' => $element['props'],
        ];
    }
}
