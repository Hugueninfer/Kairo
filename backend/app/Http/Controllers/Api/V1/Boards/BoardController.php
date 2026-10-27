<?php

namespace App\Http\Controllers\Api\V1\Boards;

use App\Http\Controllers\Controller;
use App\Http\Requests\Boards\BoardRequest;
use App\Http\Requests\Boards\ListBoardsRequest;
use App\Http\Requests\Boards\SyncBoardElementsRequest;
use App\Http\Resources\BoardResource;
use App\Models\Board;
use App\Models\User;
use App\Services\Boards\BoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BoardController extends Controller
{
    public function __construct(private readonly BoardService $boards) {}

    public function index(ListBoardsRequest $request): AnonymousResourceCollection
    {
        return BoardResource::collection($this->boards->list($request->toFilters()));
    }

    public function show(Board $board): BoardResource
    {
        return BoardResource::make($this->boards->show($board));
    }

    public function store(BoardRequest $request): JsonResponse
    {
        return BoardResource::make($this->boards->create($this->user($request), $request->toData()))
            ->response()
            ->setStatusCode(201);
    }

    public function update(BoardRequest $request, Board $board): BoardResource
    {
        return BoardResource::make($this->boards->update($board, $request->toData()));
    }

    public function destroy(Board $board): Response
    {
        $this->boards->delete($board);

        return response()->noContent();
    }

    /**
     * Autosave do canvas: devolve só a hora da gravação, o front já tem os elementos.
     */
    public function sync(SyncBoardElementsRequest $request, Board $board): JsonResponse
    {
        $saved = $this->boards->sync($this->user($request), $board, $request->toData());

        return response()->json(['data' => ['updated_at' => $saved->updated_at->toIso8601String()]]);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
