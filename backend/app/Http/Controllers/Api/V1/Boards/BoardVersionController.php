<?php

namespace App\Http\Controllers\Api\V1\Boards;

use App\Http\Controllers\Controller;
use App\Http\Requests\Boards\StoreBoardVersionRequest;
use App\Http\Resources\BoardResource;
use App\Http\Resources\BoardVersionResource;
use App\Models\Board;
use App\Models\User;
use App\Services\Boards\BoardVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BoardVersionController extends Controller
{
    public function __construct(private readonly BoardVersionService $versions) {}

    public function index(Board $board): AnonymousResourceCollection
    {
        return BoardVersionResource::collection($this->versions->list($board));
    }

    public function show(Board $board, string $version): BoardVersionResource
    {
        return BoardVersionResource::make($this->versions->show($board, $version));
    }

    /**
     * A versão é guardada na fila; a lista mostra quando ficar pronta.
     */
    public function store(StoreBoardVersionRequest $request, Board $board): JsonResponse
    {
        $this->versions->request($this->user($request), $board, $request->label());

        return response()->json(['message' => 'Versão sendo salva.'], 202);
    }

    public function restore(Request $request, Board $board, string $version): BoardResource
    {
        return BoardResource::make($this->versions->restore($this->user($request), $board, $version));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
